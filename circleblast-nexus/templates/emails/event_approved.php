<?php
defined('ABSPATH') || exit;
return [
	'subject' => 'Your event has been approved: {{event_title}}',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{first_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
Great news — your event has been approved and is now visible to all CircleBlast members! 🎉</p>

<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-left:4px solid #059669;border-radius:8px;padding:20px;margin:16px 0;">
<p style="margin:0 0 4px;font-size:17px;font-weight:700;color:#1a202c;">{{event_title}}</p>
<p style="margin:0;font-size:14px;color:#4a5568;">📅 {{event_date_formatted}}</p>
{{time_line}}
{{location_line}}
</div>

<table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0;">
<tr><td style="background-color:{{color_primary}};border-radius:6px;">
<a href="{{event_url}}" style="display:inline-block;padding:14px 28px;color:#ffffff;text-decoration:none;font-size:16px;font-weight:600;">View Event</a>
</td></tr></table>

<p style="font-size:15px;color:#333;margin-top:24px;">— CircleBlast Nexus</p>',
];
