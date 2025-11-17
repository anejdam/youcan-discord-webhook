<?php

// مهم: هدول غادي نضبطوهم من Render كـ Environment Variables
$DISCORD_URL = getenv("DISCORD_WEBHOOK_URL");
$SIGNING_KEY = getenv("YOUCAN_CLIENT_SECRET");

// نقرى البوست اللي جا من YouCan
$payload   = json_decode(file_get_contents("php://input"), true);
$signature = $_SERVER['HTTP_X_YOUCAN_SIGNATURE'] ?? "";

// نتحقق من التوقيع (سيكيوريتي)
$expected = hash_hmac('sha256', json_encode($payload), $SIGNING_KEY);
if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    echo "Invalid signature";
    exit;
}

$order    = $payload;
$shipping = $order['shipping_address'] ?? [];

$first = $shipping['first_name'] ?? "";
$last  = $shipping['last_name'] ?? "";
$phone = $shipping['phone'] ?? "";
$city  = $shipping['city'] ?? "";

// أول منتج فالطلبية
$variant = $order['variants'][0] ?? [];
$productName   = $variant['variant']['product']['name'] ?? "";
$variantValues = $variant['variant']['values'] ?? "";
$price         = $variant['price'] ?? "";
$qty           = $variant['quantity'] ?? 1;

// نقطع اللون و السايز من values (Noire,L / default)
$raw = is_array($variantValues) ? implode(",", $variantValues) : (string)$variantValues;
$clean = explode(" /", $raw)[0]; // "Noire,L"
list($color, $size) = array_pad(explode(",", $clean), 2, "");
$color = trim($color);
$size  = trim($size);

// باقي المعلومات
$ref       = $order['ref'] ?? "";
$total     = $order['total'] ?? "";
$createdAt = $order['created_at'] ?? "";
$linkShow  = $order['links']['self'] ?? "";
$linkEdit  = $order['links']['edit'] ?? "";

// إلا عندك طريقة تحسب الربح، ديرها هنا
// مثال بسيط (غير مثال): الربح = الثمن - 112
//$profit = ((float)$price - 112) * (int)$qty;
$profit = null; // خليه null إلى ما بغيتش تحسبو دابا

// نص الرسالة بالفورمات اللي بغيتي (بحال السكرين)
$description = "YouCan Store من طلبية جديدة 🛒

____________________________________
**📋 رقم الطلبية**
`{$ref}`

____________________________________
**👤 معلومات العميل**
الاسم   : {$first} {$last}
الهاتف : {$phone}
المدينة : {$city}

____________________________________
**🎯 المنتج**
{$productName}
اللون/النوع : {$color}
المقاس      : {$size}
السعر       : {$price} درهم
الكمية      : ×{$qty}

____________________________________
**💰 المجموع الإجمالي**
`{$total} درهم`";

if ($profit !== null) {
    $description .= "\n\n**💎 الربح المتوقع**\n{$profit} درهم";
}

$description .= "\n\n____________________________________
**🔗 الروابط**
[عرض الطلبية]({$linkShow}) | [تعديل الطلبية]({$linkEdit})";

// الـ embed ديال Discord
$body = [
    "content" => "",
    "embeds" => [[
        "title"       => "📦 طلبية جديدة وصلت!",
        "description" => $description,
        "color"       => 0x2ecc71 // لون أخضر، تقدر تبدلو
    ]]
];

// نرسل لـ Discord
$ch = curl_init($DISCORD_URL);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_exec($ch);

http_response_code(200);
echo "OK";
