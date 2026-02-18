<?php
defined('ABSPATH') || exit;
return [
	'subject' => 'Who should join CircleBlast? {{count}} roles we\'re looking for',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{first_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
We\'re actively looking to bring on <strong>{{count}} types of professionals</strong> to strengthen our group. Know someone who fits? Send them our way!</p>
<div style="margin:16px 0;">{{categories_list}}</div>
<p style="font-size:15px;color:#333;line-height:1.6;margin:16px 0;">
If someone comes to mind, simply reply to this email with their name and how you know them — we\'ll take it from there.</p>
<p style="font-size:15px;color:#333;margin:16px 0 0;">— The CircleBlast Team</p>',
];
