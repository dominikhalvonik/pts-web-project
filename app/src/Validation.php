<?php
declare(strict_types=1);
namespace TestLab;

final class Validation
{
    public static function text(array $input, string $field, int $max, bool $required=true): string
    {
        $value=$input[$field]??'';
        if (!is_string($value)) { self::bad($field,'Očakáva sa text.'); }
        $value=trim($value);
        $length=preg_match_all('/./us', $value);
        if ($length === false || ($required && $value==='') || $length>$max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            self::bad($field,"Neplatná hodnota; najviac {$max} znakov.");
        }
        return $value;
    }
    public static function email(array $input, string $field): string
    {
        $email=strtolower(self::text($input,$field,190));
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) { self::bad($field,'Neplatná e-mailová adresa.'); }
        return $email;
    }
    public static function password(array $input): string
    {
        $password=$input['password']??null;
        if (!is_string($password) || strlen($password)<8 || strlen($password)>72 || str_contains($password,"\0")) {
            self::bad('password','Heslo musí mať 8 až 72 bajtov.');
        }
        return $password;
    }
    public static function cart(array $input): array
    {
        $items=$input['items']??null;
        if (!is_array($items) || !array_is_list($items) || count($items)<1 || count($items)>30) {
            self::bad('items','Košík musí obsahovať 1 až 30 rôznych produktov.');
        }
        $normalized=[];
        foreach ($items as $item) {
            if (!is_array($item)) { self::bad('items','Neplatná položka.'); }
            $sku=self::text($item,'sku',32);
            if (!preg_match('/^[A-Z0-9-]+$/D',$sku)) { self::bad('sku','Neplatné SKU.'); }
            $qty=$item['quantity']??null;
            if (!is_int($qty) || $qty<1 || $qty>20) { self::bad('quantity','Množstvo musí byť celé číslo od 1 do 20.'); }
            if (isset($normalized[$sku])) { self::bad('items','Produkt môže byť uvedený iba raz.'); }
            $normalized[$sku]=['sku'=>$sku,'quantity'=>$qty];
        }
        ksort($normalized,SORT_STRING);
        $coupon=strtoupper(self::text($input,'coupon_code',32,false));
        if (!in_array($coupon,['','STUDENT10'],true)) { throw new ApiError(422,'INVALID_COUPON','Zľavový kód nie je platný.'); }
        $delivery=self::text($input,'delivery_method',16);
        if (!in_array($delivery,['courier','pickup'],true)) { self::bad('delivery_method','Zvoľte kuriéra alebo osobný odber.'); }
        return ['items'=>array_values($normalized),'coupon_code'=>$coupon,'delivery_method'=>$delivery];
    }
    public static function order(array $input): array
    {
        $cart=self::cart($input);
        $customer=$input['customer']??null;
        if (!is_array($customer)) { self::bad('customer','Chýbajú kontaktné údaje.'); }
        $shipping=$cart['delivery_method']==='courier';
        $c=['name'=>self::text($customer,'name',100),'email'=>self::email($customer,'email'),
            'address'=>self::text($customer,'address',190,$shipping),
            'city'=>self::text($customer,'city',100,$shipping),
            'postal_code'=>self::text($customer,'postal_code',10,$shipping)];
        if ($shipping && !preg_match('/^\d{3}\s?\d{2}$/D',$c['postal_code'])) { self::bad('postal_code','Použite PSČ vo formáte 12345 alebo 123 45.'); }
        $c['postal_code']=str_replace(' ','',$c['postal_code']);
        if (!$shipping) { $c['address']=''; $c['city']=''; $c['postal_code']=''; }
        if (($input['accept_terms']??null)!==true) { self::bad('accept_terms','Potvrďte, že ide o simulovanú objednávku.'); }
        $expected=$input['expected_total_cents']??null;
        if (!is_int($expected) || $expected<0) { self::bad('expected_total_cents','Chýba očakávaná suma v centoch.'); }
        return $cart+['customer'=>$c,'note'=>self::text($input,'note',500,false),'accept_terms'=>true,'expected_total_cents'=>$expected];
    }
    private static function bad(string $field,string $message): never
    { throw new ApiError(422,'VALIDATION_ERROR',$message,[$field=>$message]); }
}
