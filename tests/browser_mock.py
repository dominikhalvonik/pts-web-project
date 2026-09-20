#!/usr/bin/env python3
"""Frontend-only checks against an in-memory API mock. NOT a PHP/MariaDB integration test.
Usage: python tests/browser_mock.py --url http://127.0.0.1:8090/
Requires: pip install playwright; playwright install chromium
"""
from __future__ import annotations
import argparse, copy, json, shutil, uuid
from pathlib import Path
from urllib.parse import urlparse, parse_qs
from playwright.sync_api import sync_playwright, expect

ROOT = Path(__file__).resolve().parents[1]
PRODUCT_ROWS = [
 ['KB-001','Mechanická klávesnica','Kompaktná klávesnica s USB pripojením.','Periférie',3490,100],
 ['MS-001','Bezdrôtová myš','Ľahká ergonomická myš na každodennú prácu.','Periférie',1990,100],
 ['USB-001','USB-C kábel','Dvojmetrový kábel na nabíjanie a prenos dát.','Príslušenstvo',1250,100],
 ['MON-001','Monitor 24 palcov','IPS monitor do pracovného a študijného prostredia.','Monitory',14900,20],
 ['CAM-001','Webová kamera','Kamera s krytkou objektívu na videohovory.','Periférie',5900,40],
 ['PAD-001','Podložka pod myš','Protišmyková podložka v minimalistickom dizajne.','Príslušenstvo',995,100],
 ['HUB-001','USB-C rozbočovač','Rozbočovač s viacerými portmi. Aktuálne vypredaný.','Príslušenstvo',2990,0],
 ['BAG-001','Obal na notebook','Ochranný obal na notebook s uhlopriečkou 14 palcov.','Príslušenstvo',2500,30],
]

class MockAPI:
    def __init__(self):
        self.user = None
        self.products = [dict(zip(['sku','name','description','category','price_cents','stock'], p),active=1) for p in PRODUCT_ROWS]
        self.orders: dict[str,dict] = {}
        self.order_posts = 0
    def intercept(self, route):
        req = route.request; qs = parse_qs(urlparse(req.url).query); path = qs.get('route',[''])[0]
        body = req.post_data_json if req.post_data else {}
        def ok(data,status=200): route.fulfill(status=status,content_type='application/json',body=json.dumps({'data':data,'meta':{}},ensure_ascii=False))
        def fail(code,message,status=422): route.fulfill(status=status,content_type='application/json',body=json.dumps({'error':{'code':code,'message':message,'details':{}}}))
        if path == '/auth/me': return ok({'user':self.user,'csrf_token':'mock-csrf'})
        if path == '/auth/login':
            if body.get('password') != 'Student123!': return fail('INVALID_CREDENTIALS','Nesprávny e-mail alebo heslo.',401)
            self.user={'id':1,'workspace_id':1,'workspace_code':'demo','name':'Študent Demo','email':'student@example.test'}
            return ok({'user':self.user,'csrf_token':'mock-new-csrf'})
        if path == '/auth/logout': self.user=None; return ok({'logged_out':True})
        if path == '/products':
            q=qs.get('q',[''])[0].lower(); category=qs.get('category',[''])[0]; stock=qs.get('in_stock',['0'])[0]=='1'
            products=[p for p in self.products if (not q or q in p['name'].lower() or q in p['sku'].lower()) and (not category or p['category']==category) and (not stock or p['stock']>0)]
            return ok({'products':products})
        if path == '/cart/quote' or (path == '/orders' and req.method == 'POST'):
            if body.get('coupon_code','') not in ('','STUDENT10'): return fail('INVALID_COUPON','Zľavový kód nie je platný.')
            items=[]
            for item in body['items']:
                p=next(p for p in self.products if p['sku']==item['sku'])
                if p['stock']<item['quantity']: return fail('OUT_OF_STOCK','Nedostatočné skladové množstvo.',409)
                items.append({'sku':p['sku'],'name':p['name'],'unit_price_cents':p['price_cents'],'quantity':item['quantity'],'line_total_cents':p['price_cents']*item['quantity']})
            subtotal=sum(i['line_total_cents'] for i in items); discount=(subtotal+5)//10 if body.get('coupon_code')=='STUDENT10' else 0
            shipping=0 if body['delivery_method']=='pickup' or subtotal-discount>=10000 else 490
            quote={'items':items,'currency':'EUR','subtotal_cents':subtotal,'discount_cents':discount,'shipping_cents':shipping,'total_cents':subtotal-discount+shipping,'coupon_code':body.get('coupon_code',''),'delivery_method':body['delivery_method']}
            if path=='/cart/quote': return ok(quote)
            self.order_posts+=1
            oid=str(uuid.uuid4()); c=body['customer']; order={**quote,'id':oid,'order_number':'TL-'+oid.replace('-','').upper(),'status':'created','created_at':'2026-09-20 09:00:00','customer_name':c['name'],'customer_email':c['email'],'address_line':c['address'],'city':c['city'],'postal_code':c['postal_code'],'note':body['note']}
            self.orders[oid]=order
            for item in items:
                next(p for p in self.products if p['sku']==item['sku'])['stock']-=item['quantity']
            return ok(order,201)
        if path=='/orders': return ok({'orders':list(self.orders.values()),'page':1,'page_size':20,'total':len(self.orders)})
        if path.startswith('/orders/'):
            oid=path.split('/')[2]
            if oid not in self.orders: return fail('ORDER_NOT_FOUND','Objednávka neexistuje.',404)
            order=self.orders[oid]
            if path.endswith('/cancel'):
                if order['status']=='created':
                    for item in order['items']: next(p for p in self.products if p['sku']==item['sku'])['stock']+=item['quantity']
                order['status']='cancelled'
            return ok(order)
        raise AssertionError('Unexpected mock route: '+path)

def main():
    parser=argparse.ArgumentParser();parser.add_argument('--url',default='http://127.0.0.1:8090/');parser.add_argument('--chromium');args=parser.parse_args()
    result_dir=ROOT/'tests/results';result_dir.mkdir(exist_ok=True)
    checks=[]
    def passed(name): checks.append(name);print('[PASS]',name)
    with sync_playwright() as p:
        executable=args.chromium or shutil.which('chromium')
        browser=p.chromium.launch(headless=True,executable_path=executable,args=['--no-sandbox'])
        context=browser.new_context(viewport={'width':1440,'height':1000},locale='sk-SK')
        mock=MockAPI();context.route('**/api/index.php*',mock.intercept)
        page=context.new_page();errors=[];page.on('pageerror',lambda e: errors.append(str(e)))
        page.goto(args.url);expect(page.get_by_test_id('catalog-ready')).to_be_visible()
        expect(page.locator('[data-testid^="product-card-"]')).to_have_count(8);passed('Catalog renders eight Vue product cards')
        expect(page.get_by_test_id('add-to-cart-HUB-001')).to_be_disabled();passed('Out-of-stock button is disabled')
        page.screenshot(path=str(result_dir/'catalog-desktop.png'),full_page=True)
        page.get_by_test_id('search-input').fill('USB-001');page.get_by_test_id('search-submit').click()
        expect(page.locator('[data-testid^="product-card-"]')).to_have_count(1);passed('Catalog search filters by SKU')
        page.get_by_test_id('search-input').fill('nothing-found');page.get_by_test_id('search-submit').click()
        expect(page.get_by_test_id('catalog-empty')).to_be_visible();passed('Empty catalog result state')
        page.get_by_test_id('nav-login').click();page.get_by_test_id('login-email').fill('student@example.test');page.get_by_test_id('login-password').fill('WrongPass123!');page.get_by_test_id('login-submit').click()
        expect(page.get_by_test_id('login-error')).to_be_visible();passed('Invalid credentials displayed')
        page.get_by_test_id('login-password').fill('Student123!');page.get_by_test_id('login-submit').click();expect(page.get_by_test_id('catalog-ready')).to_be_visible()
        expect(page.get_by_test_id('current-user')).to_have_text('Študent Demo');passed('Successful login updates account and navigation')
        page.get_by_test_id('nav-orders').click();expect(page.get_by_test_id('orders-empty')).to_be_visible();passed('Empty order history state')
        page.get_by_test_id('nav-catalog').click();expect(page.get_by_test_id('catalog-ready')).to_be_visible()
        page.get_by_test_id('add-to-cart-KB-001').click();page.get_by_test_id('add-to-cart-MS-001').click();expect(page.get_by_test_id('cart-count')).to_have_text('2');passed('Add-to-cart updates basket count')
        page.get_by_test_id('nav-cart').click();page.get_by_test_id('quantity-KB-001').fill('2');page.get_by_test_id('quantity-KB-001').blur();expect(page.get_by_test_id('cart-count')).to_have_text('3');passed('Quantity update persists')
        page.get_by_test_id('coupon-input').fill('BAD');page.get_by_test_id('coupon-apply').click();expect(page.get_by_test_id('quote-error')).to_be_visible();passed('Invalid coupon error from API displayed')
        page.get_by_test_id('coupon-input').fill('STUDENT10');page.get_by_test_id('coupon-apply').click();expect(page.get_by_test_id('quote-total')).to_have_attribute('data-cents','8563');passed('Reference quote renders 85.63 EUR')
        page.get_by_test_id('coupon-apply').click();expect(page.get_by_test_id('quote-total')).to_have_attribute('data-cents','8563');passed('Applying unchanged coupon does not lose quote')
        page.get_by_test_id('delivery-method').select_option('pickup');expect(page.get_by_test_id('quote-total')).to_have_attribute('data-cents','8073');passed('Pickup quote removes shipping')
        page.get_by_test_id('delivery-method').select_option('courier');expect(page.get_by_test_id('quote-total')).to_have_attribute('data-cents','8563')
        page.reload();expect(page.get_by_test_id('quantity-KB-001')).to_have_value('2');expect(page.get_by_test_id('quote-total')).to_have_attribute('data-cents','8563');passed('Cart and coupon survive page reload')
        page.get_by_test_id('proceed-checkout').click();expect(page.get_by_test_id('checkout-form')).to_be_visible();expect(page.get_by_test_id('quote-total')).to_have_attribute('data-cents','8563');passed('Checkout loads authoritative quote')
        page.get_by_test_id('customer-address').fill('Testovacia 12');page.get_by_test_id('customer-city').fill('Žilina');page.get_by_test_id('customer-postal-code').fill('010 01')
        note='<img src=x onerror="window.__xss=true"> test';page.get_by_test_id('order-note').fill(note);page.get_by_test_id('accept-terms').check();page.get_by_test_id('place-order').click()
        expect(page.get_by_test_id('order-status')).to_have_attribute('data-status','created');expect(page.get_by_test_id('order-total')).to_have_attribute('data-cents','8563');passed('Checkout navigates to order detail')
        expect(page.get_by_test_id('cart-count')).to_have_text('0');assert mock.order_posts==1;passed('Successful order clears cart and sends one request')
        expect(page.get_by_test_id('order-note-text')).to_have_text(note);assert page.evaluate('window.__xss === undefined');assert page.get_by_test_id('order-note-text').locator('img').count()==0;passed('Vue escapes HTML supplied in order note')
        page.screenshot(path=str(result_dir/'order-desktop.png'),full_page=True)
        page.get_by_test_id('cancel-order').click();page.get_by_test_id('keep-order').click();expect(page.get_by_test_id('order-status')).to_have_attribute('data-status','created');passed('Cancellation can be declined')
        page.get_by_test_id('cancel-order').click();page.get_by_test_id('confirm-cancel').click();expect(page.get_by_test_id('order-status')).to_have_attribute('data-status','cancelled');passed('Confirmed cancellation updates status')
        page.get_by_test_id('nav-orders').click();expect(page.get_by_test_id('orders-ready')).to_be_visible();expect(page.locator('[data-testid^="order-row-"]')).to_have_count(1);passed('History displays created order')
        page.get_by_test_id('logout-button').click();expect(page.get_by_test_id('login-form')).to_be_visible();passed('Logout returns to login')
        page.goto(args.url+'#/checkout');expect(page.get_by_test_id('login-form')).to_be_visible();passed('Unauthenticated checkout is redirected')
        page.goto(args.url+'#/not-found');expect(page.get_by_role('heading',name='Stránka neexistuje.')).to_be_visible();passed('Unknown route has explicit not-found view')
        assert not errors,errors;passed('No JavaScript page errors across tested desktop screens')
        mobile=browser.new_context(viewport={'width':390,'height':844},device_scale_factor=1,is_mobile=True,locale='sk-SK')
        mobile.route('**/api/index.php*',MockAPI().intercept);mp=mobile.new_page();mp.goto(args.url);expect(mp.get_by_test_id('catalog-ready')).to_be_visible()
        assert mp.evaluate('document.documentElement.scrollWidth <= innerWidth'),mp.evaluate('document.documentElement.scrollWidth');passed('390px mobile catalog has no horizontal overflow')
        mp.screenshot(path=str(result_dir/'catalog-mobile.png'),full_page=True)
        browser.close()
    (result_dir/'browser-mock-results.json').write_text(json.dumps({'type':'frontend-only API mock','passed':len(checks),'failed':0,'checks':checks},indent=2,ensure_ascii=False)+'\n')
    print(f'\n{len(checks)} frontend-only checks passed. PHP/MariaDB integration NOT verified by this script.')
if __name__=='__main__': main()
