#!/usr/bin/env python3
"""Live HTTP integration checks. Uses ONLY newly created isolated fixtures.
Requires Python 3.10+, a running PHP/MariaDB application, APP_ENV=testing,
ENABLE_TEST_API=true and TEST_API_KEY (environment variable).
Does not use browser mocks. Does not modify the shared demo workspace.
"""
from __future__ import annotations
import argparse, copy, http.cookiejar, json, os, sys, uuid
from concurrent.futures import ThreadPoolExecutor
from urllib.error import HTTPError
from urllib.parse import urlencode, urljoin
from urllib.request import Request, build_opener, HTTPCookieProcessor

class Client:
    def __init__(self, base: str, token: str = '') -> None:
        self.endpoint=urljoin(base.rstrip('/')+'/', 'api/index.php')
        self.token=token; self.cookies=http.cookiejar.CookieJar()
        self.opener=build_opener(HTTPCookieProcessor(self.cookies))
    def send(self, path: str, method: str='GET', body: dict|None=None,
             headers: dict|None=None, query: dict|None=None) -> tuple[int,dict,dict]:
        h={'Accept':'application/json'}
        if self.token:h['Authorization']='Bearer '+self.token
        if body is not None:h['Content-Type']='application/json'
        h.update(headers or {})
        url=self.endpoint+'?'+urlencode({'route':path,**(query or {})})
        req=Request(url,method=method,headers=h,data=None if body is None else json.dumps(body).encode())
        try: response=self.opener.open(req,timeout=30)
        except HTTPError as e: response=e
        with response:
            raw=response.read()
            try: value=json.loads(raw)
            except json.JSONDecodeError: raise RuntimeError(f'{path}: server did not return JSON (HTTP {response.code}).')
            return response.code,value,dict(response.headers)

def main()->int:
    parser=argparse.ArgumentParser();parser.add_argument('--url',default='http://127.0.0.1:8080/');args=parser.parse_args()
    key=os.environ.get('TEST_API_KEY','')
    if len(key)<32:
        print('Set TEST_API_KEY (at least 32 characters) to match the isolated test server.',file=sys.stderr);return 2
    admin=Client(args.url);runs=[];checks=[];success=False
    def expect(name:str,response:tuple[int,dict,dict],status:int=200,code:str|None=None)->dict:
        actual,body,_=response
        if actual!=status:raise AssertionError(f'{name}: expected HTTP {status}, got {actual}: {body}')
        if code and body.get('error',{}).get('code')!=code:raise AssertionError(f'{name}: unexpected error code: {body}')
        checks.append(name);print('[PASS]',name);return body.get('data',{})
    def check(name:str,condition:bool)->None:
        if not condition:raise AssertionError(name)
        checks.append(name);print('[PASS]',name)
    def fixture()->tuple[Client,dict]:
        data=expect('Create isolated fixture',admin.send('/testing/fixtures','POST',{},headers={'X-Test-Key':key}),201)
        runs.append(data['workspace_code'])
        token=expect('Issue bearer token',admin.send('/auth/token','POST',{'email':data['email'],'password':data['password']}),201)
        return Client(args.url,token['token']),data
    try:
        expect('Health endpoint',admin.send('/health'))
        expect('Protected order list rejects anonymous access',admin.send('/orders'),401,'UNAUTHENTICATED')
        a,fa=fixture();b,fb=fixture()
        products=expect('List products',a.send('/products'))['products'];check('Eight seeded products',len(products)==8)
        expect('Foreign token rejected',Client(args.url,'0'*64).send('/orders'),401,'UNAUTHENTICATED')
        cart={'items':[{'sku':'KB-001','quantity':2},{'sku':'MS-001','quantity':1}], 'coupon_code':'STUDENT10','delivery_method':'courier'}
        quote=expect('Reference quote',a.send('/cart/quote','POST',cart));check('Reference price is 8563 cents',quote['total_cents']==8563)
        invalid=copy.deepcopy(cart);invalid['items'][0]['quantity']=1.5
        expect('Fractional quantity rejected',a.send('/cart/quote','POST',invalid),422,'VALIDATION_ERROR')
        invalid=copy.deepcopy(cart);invalid['items']+=invalid['items'][:1]
        expect('Duplicate SKU rejected',a.send('/cart/quote','POST',invalid),422,'VALIDATION_ERROR')
        invalid=copy.deepcopy(cart);invalid['coupon_code']='FREE'
        expect('Invalid coupon rejected',a.send('/cart/quote','POST',invalid),422,'INVALID_COUPON')
        invalid=copy.deepcopy(cart);invalid['items']=[{'sku':'HUB-001','quantity':1}]
        expect('Zero stock rejected',a.send('/cart/quote','POST',invalid),409,'OUT_OF_STOCK')
        payload={**cart,'customer':{'name':'Testovací Študent','email':fa['email'],'address':'Testovacia 12','city':'Žilina','postal_code':'010 01'},'note':'integration baseline','accept_terms':True,'expected_total_cents':8563}
        bad=copy.deepcopy(payload);bad['expected_total_cents']=1
        expect('Stale or manipulated total rejected',a.send('/orders','POST',bad,headers={'Idempotency-Key':str(uuid.uuid4())}),409,'PRICE_CHANGED')
        expect('Missing idempotency key rejected',a.send('/orders','POST',payload),422,'IDEMPOTENCY_KEY_REQUIRED')
        idem=str(uuid.uuid4());created=expect('Create reference order',a.send('/orders','POST',payload,headers={'Idempotency-Key':idem}),201)
        oid=created['id'];check('Stored response has reference total',created['total_cents']==8563)
        detail=expect('Read order detail',a.send('/orders/'+oid));check('Order item quantity is two',next(i for i in detail['items'] if i['sku']=='KB-001')['quantity']==2)
        def stocks(client:Client)->dict:
            status,data,_=client.send('/products');assert status==200;return {p['sku']:p['stock'] for p in data['data']['products']}
        check('Stock reduced exactly once',stocks(a)['KB-001']==98 and stocks(a)['MS-001']==99)
        replay_response=a.send('/orders','POST',payload,headers={'Idempotency-Key':idem})
        replay=expect('Idempotent replay',replay_response);check('Replay reuses order identity',replay['id']==oid and replay_response[1]['meta']['replayed'] is True)
        check('Replay does not reduce stock again',stocks(a)['KB-001']==98)
        changed=copy.deepcopy(payload);changed['note']='different semantics'
        expect('Idempotency conflict rejected',a.send('/orders','POST',changed,headers={'Idempotency-Key':idem}),409,'IDEMPOTENCY_CONFLICT')
        expect('Other account cannot read order',b.send('/orders/'+oid),404,'ORDER_NOT_FOUND')
        expect('Other account cannot cancel order',b.send('/orders/'+oid+'/cancel','POST',{}),404,'ORDER_NOT_FOUND')
        check('Fixture inventories are isolated',stocks(b)['KB-001']==100)
        canceled=expect('Cancel order',a.send('/orders/'+oid+'/cancel','POST',{}));check('Cancelled status persisted',canceled['status']=='cancelled')
        expect('Second cancellation is safe',a.send('/orders/'+oid+'/cancel','POST',{}));check('Stock restored only once',stocks(a)['KB-001']==100 and stocks(a)['MS-001']==100)
        boundary={'items':[{'sku':'BAG-001','quantity':4}],'coupon_code':'','delivery_method':'courier'}
        q=expect('Shipping boundary quote',a.send('/cart/quote','POST',boundary));check('100 EUR has zero shipping',q['shipping_cents']==0)
        boundary['coupon_code']='STUDENT10';q=expect('Shipping after discount quote',a.send('/cart/quote','POST',boundary));check('Shipping threshold applies after discount',q['total_cents']==9490)
        rounding={'items':[{'sku':'PAD-001','quantity':1}],'coupon_code':'STUDENT10','delivery_method':'pickup'}
        q=expect('Half-up rounding quote',a.send('/cart/quote','POST',rounding));check('99.5-cent discount rounds to 100',q['discount_cents']==100)
        # Session authentication, separate from token authentication.
        web=Client(args.url);anon=expect('Get CSRF token',web.send('/auth/me'));creds={'email':fa['email'],'password':fa['password']}
        expect('Session login requires CSRF',web.send('/auth/login','POST',creds),403,'CSRF_FAILED')
        logged=expect('Session login with CSRF',web.send('/auth/login','POST',creds,headers={'X-CSRF-Token':anon['csrf_token']}))
        check('CSRF token rotates on login',logged['csrf_token']!=anon['csrf_token'])
        expect('Authenticated mutation still requires CSRF',web.send('/orders','POST',payload,headers={'Idempotency-Key':str(uuid.uuid4())}),403,'CSRF_FAILED')
        expect('Session logout',web.send('/auth/logout','POST',{},headers={'X-CSRF-Token':logged['csrf_token']}))
        expect('Logged-out session cannot read orders',web.send('/orders'),401,'UNAUTHENTICATED')
        # Two different order keys compete for only 20 monitors. Only one request for 15 can win.
        concurrent=copy.deepcopy(payload);concurrent['items']=[{'sku':'MON-001','quantity':15}];concurrent['coupon_code']='';concurrent['expected_total_cents']=223500
        def submit_once(_):
            return Client(args.url,a.token).send('/orders','POST',concurrent,headers={'Idempotency-Key':str(uuid.uuid4())})
        with ThreadPoolExecutor(max_workers=2) as pool: responses=list(pool.map(submit_once,range(2)))
        check('Competing orders do not oversell',sorted(r[0] for r in responses)==[201,409])
        check('Competing orders leave five monitors',stocks(a)['MON-001']==5)
        win=next(r[1]['data'] for r in responses if r[0]==201)
        expect('Cancel winning concurrent order',a.send('/orders/'+win['id']+'/cancel','POST',{}))
        check('Concurrent scenario restores stock',stocks(a)['MON-001']==20)
        success=True
    except Exception as e:
        print('[FAIL]',str(e),file=sys.stderr)
    finally:
        for run in reversed(runs):
            try:expect('Delete owned fixture '+run,admin.send('/testing/fixtures/'+run,'DELETE',headers={'X-Test-Key':key}))
            except Exception as e:print('[CLEANUP FAILED]',str(e),file=sys.stderr);success=False
    print(f'\n{len(checks)} checks passed; overall: '+('PASS' if success else 'FAIL'))
    return 0 if success else 1
if __name__=='__main__':sys.exit(main())
