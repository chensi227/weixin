<?php
header('Content-Type: text/html; charset=utf-8');

define('APPID', 'wxea5ececa098df8b2');
define('APPSECRET', 'e5541a765cf6d05bdfcc5eb97c09df01');
define('TOKEN', 'chensi227');

require './WeChat.class.php';

$wechat = new WeChat(APPID, APPSECRET, TOKEN);

// 生成QRCode
$image_content = $wechat->getQRCode(42);
// 存储成图片
file_put_contents('./qrcode.jpg', $image_content);
// 输出到浏览器显示
header('Content-Type: image/jpeg');
echo $image_content;
