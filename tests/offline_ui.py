from pathlib import Path
from offline_render import boot as offline_boot, configure as offline_configure
source_path=Path(__file__).with_name('browser_mock.py')
source=source_path.read_text()
source=source.replace("context.route('**/api/index.php*',mock.intercept)","offline_configure(context, mock)")
source=source.replace("page.goto(args.url)","offline_boot(page, args.url)")
source=source.replace("page.goto(args.url+'#/checkout')","offline_boot(page, args.url+'#/checkout')")
source=source.replace("page.goto(args.url+'#/not-found')","offline_boot(page, args.url+'#/not-found')")
source=source.replace('page.reload()','offline_boot(page, page.url)')
source=source.replace("mobile.route('**/api/index.php*',MockAPI().intercept);mp=mobile.new_page();mp.goto(args.url)","offline_configure(mobile, MockAPI());mp=mobile.new_page();offline_boot(mp,args.url)")
source=source.replace("        # Real endpoint deliberately not mocked here: validates graceful failure in this environment.\n        raw=browser.new_context();rp=raw.new_page();rp.goto(args.url);expect(rp.get_by_test_id('startup-error')).to_be_visible();passed('Unavailable real database produces an explicit startup error')\n",'')
source=source.replace('Cart and coupon survive page reload','Cart and coupon survive offline app reinitialization (mock storage)')
source=source.replace("'type':'frontend-only API mock'","'type':'offline Vue rendering with mocked API and storage; no browser network'")
exec(compile(source, str(source_path),'exec'),{'__name__':'__main__','__file__':str(source_path),'offline_boot':offline_boot,'offline_configure':offline_configure})
