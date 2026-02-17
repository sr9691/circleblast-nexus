<?php
defined('ABSPATH') || exit;
return [
	'subject' => 'Reminder: 1:1 with {{other_name}} coming up',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{first_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
Just a reminder that your 1:1 meeting with <strong>{{other_name}}</strong> is scheduled for <strong>{{scheduled_at}}</strong>.</p>
<table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0;">
<tr><td style="background-color:#2563eb;border-radius:6px;">
<a href="{{meetings_url}}" style="display:inline-block;padding:14px 28px;color:#ffffff;text-decoration:none;font-size:16px;font-weight:600;">View Your Meetings</a>
</td></tr></table>
<p style="font-size:15px;color:#333;">— The CircleBlast Team</p>',
];
