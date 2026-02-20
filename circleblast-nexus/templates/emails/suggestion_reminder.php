<?php
defined('ABSPATH') || exit;
return [
	'subject' => 'Reminder: Respond to your 1:1 match with {{other_name}}',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{first_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
Just a friendly nudge — you haven\'t responded to your suggested 1:1 with <strong>{{other_name}}</strong> yet. Don\'t miss out on a great conversation!</p>
<table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0;">
<tr><td style="background-color:{{color_primary}};border-radius:6px;">
<a href="{{meetings_url}}" style="display:inline-block;padding:14px 28px;color:#ffffff;text-decoration:none;font-size:16px;font-weight:600;">View &amp; Respond</a>
</td></tr></table>
<p style="font-size:15px;color:#333;">— The CircleBlast Team</p>',
];
