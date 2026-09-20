<?php
declare(strict_types=1);
namespace TestLab;

/** Pure domain function. All monetary arithmetic is integer arithmetic in EUR cents. */
final class Pricing
{
    public static function quote(array $cart, array $productsBySku, string $fault='none'): array
    {
        $subtotal=0; $lines=[];
        foreach ($cart['items'] as $item) {
            $p=$productsBySku[$item['sku']]??null;
            if (!$p || !(bool)$p['active']) { throw new ApiError(404,'PRODUCT_NOT_FOUND','Produkt nie je dostupný.',['sku'=>$item['sku']]); }
            if ((int)$p['stock']<$item['quantity']) { throw new ApiError(409,'OUT_OF_STOCK','Nedostatočné skladové množstvo.',['sku'=>$item['sku'],'available'=>(int)$p['stock']]); }
            $line=(int)$p['price_cents']*$item['quantity']; $subtotal+=$line;
            $lines[]=['sku'=>$p['sku'],'name'=>$p['name'],'unit_price_cents'=>(int)$p['price_cents'],
                'quantity'=>$item['quantity'],'line_total_cents'=>$line];
        }
        // Round once, on the entire merchandise subtotal, half up to the nearest cent.
        $discount=$cart['coupon_code']==='STUDENT10'
            ? intdiv($subtotal+($fault==='discount_rounding'?0:5),10) : 0;
        $afterDiscount=$subtotal-$discount;
        $free=$fault==='shipping_boundary' ? $afterDiscount>10000 : $afterDiscount>=10000;
        $shipping=$cart['delivery_method']==='pickup'||$free ? 0 : 490;
        return ['items'=>$lines,'currency'=>'EUR','subtotal_cents'=>$subtotal,'discount_cents'=>$discount,
            'shipping_cents'=>$shipping,'total_cents'=>$afterDiscount+$shipping,
            'coupon_code'=>$cart['coupon_code'],'delivery_method'=>$cart['delivery_method']];
    }
}
