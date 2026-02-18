<?php
defined('ABSPATH') || exit;
return [
	'subject' => '{{requester_name}} wants to meet 1:1',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{first_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
<strong>{{requester_name}}</strong> ({{requester_title}}) would like to connect with you for a 1:1 meeting.</p>
<table role="presentation" cellspacing="0" cellpadding="0"><tr>
<td style="background-color:#5b2d6e;border-radius:6px;margin-right:12px;">
<a href="{{accept_url}}" style="display:inline-block;padding:12px 24px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;">Accept</a>
</td>
<td style="width:12px;"></td>
<td style="background-color:#e2e8f0;border-radius:6px;">
<a href="{{decline_url}}" style="display:inline-block;padding:12px 24px;color:#4a5568;text-decoration:none;font-size:15px;font-weight:500;">Decline</a>
</td>
</tr></table>
<p style="font-size:13px;color:#a0aec0;margin:16px 0 0;">No login required — just click to respond.</p>
<p style="font-size:15px;color:#333;margin:16px 0 0;">— The CircleBlast Team</p>',
];
