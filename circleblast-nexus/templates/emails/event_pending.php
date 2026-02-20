<?php
defined('ABSPATH') || exit;
return [
	'subject' => 'New event submitted: {{event_title}}',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{admin_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
A member has submitted a new event for approval:</p>
<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin:16px 0;">
<p style="margin:0 0 4px;font-size:16px;font-weight:600;">{{event_title}}</p>
<p style="margin:0;font-size:14px;color:#4a5568;">📅 {{event_date}}</p>
</div>
<table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0;">
<tr><td style="background-color:{{color_primary}};border-radius:6px;">
<a href="{{review_url}}" style="display:inline-block;padding:14px 28px;color:#ffffff;text-decoration:none;font-size:16px;font-weight:600;">Review Event</a>
</td></tr></table>
<p style="font-size:15px;color:#333;">— CircleBlast Nexus</p>',
];
