<?php
defined('ABSPATH') || exit;
return [
	'subject' => 'Reminder: Respond to your 1:1 match with {{other_name}}',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{first_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
Just a friendly nudge — you haven\'t responded to your suggested 1:1 with <strong>{{other_name}}</strong> yet. Don\'t miss out on a great conversation!</p>
<table role="presentation" cellspacing="0" cellpadding="0"><tr>
<td style="background-color:{{color_primary}};border-radius:6px;margin-right:12px;">
<a href="{{accept_url}}" style="display:inline-block;padding:12px 24px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;">Accept</a>
</td>
<td style="width:12px;"></td>
<td style="background-color:#e2e8f0;border-radius:6px;">
<a href="{{decline_url}}" style="display:inline-block;padding:12px 24px;color:#4a5568;text-decoration:none;font-size:15px;font-weight:500;">Decline</a>
</td>
</tr></table>
<p style="font-size:13px;color:#a0aec0;margin:16px 0 0;">Or <a href="{{meetings_url}}" style="color:{{color_primary}};">view in your portal</a>.</p>
<p style="font-size:15px;color:#333;margin:16px 0 0;">— The Circle Team</p>',
];
