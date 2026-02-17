<?php
defined('ABSPATH') || exit;
return [
	'subject' => 'Your 1:1 match this month: {{other_name}}',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{first_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
We\'ve matched you with <strong>{{other_name}}</strong> for a 1:1 meeting this month!</p>
<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin:16px 0;">
<p style="margin:0 0 4px;font-size:15px;"><strong>{{other_name}}</strong></p>
<p style="margin:0 0 4px;font-size:14px;color:#4a5568;">{{other_title}}</p>
<p style="margin:0;font-size:14px;color:#718096;">{{other_bio}}</p>
</div>
<p style="font-size:15px;color:#333;margin:0 0 20px;">Would you like to connect?</p>
<table role="presentation" cellspacing="0" cellpadding="0"><tr>
<td style="background-color:#2563eb;border-radius:6px;margin-right:12px;">
<a href="{{accept_url}}" style="display:inline-block;padding:12px 24px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;">Accept</a>
</td>
<td style="width:12px;"></td>
<td style="background-color:#e2e8f0;border-radius:6px;">
<a href="{{decline_url}}" style="display:inline-block;padding:12px 24px;color:#4a5568;text-decoration:none;font-size:15px;font-weight:500;">Decline</a>
</td>
</tr></table>
<p style="font-size:13px;color:#a0aec0;margin:16px 0 0;">Or <a href="{{meetings_url}}" style="color:#2563eb;">view in your portal</a>.</p>
<p style="font-size:15px;color:#333;margin:16px 0 0;">— The CircleBlast Team</p>',
];
