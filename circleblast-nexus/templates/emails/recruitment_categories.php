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
If someone comes to mind, submit a quick referral — just a name is all we need to get started.</p>
<p style="margin:16px 0;text-align:center;">
<a href="{{referral_url}}" style="display:inline-block;padding:12px 28px;background:#5b2d6e;color:#fff;text-decoration:none;border-radius:6px;font-size:14px;font-weight:600;">Submit a Referral</a>
</p>
<p style="font-size:15px;color:#333;margin:16px 0 0;">— The CircleBlast Team</p>',
];
