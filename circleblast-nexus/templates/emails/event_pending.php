<?php
defined('ABSPATH') || exit;
return [
	'subject' => 'New event submitted: {{event_title}}',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{admin_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
<strong>{{submitter_name}}</strong> has submitted a new event for your approval:</p>

<div style="background:#f8fafc;border:1px solid #e2e8f0;border-left:4px solid {{color_primary}};border-radius:8px;padding:20px;margin:16px 0;">
<p style="margin:0 0 10px;font-size:18px;font-weight:700;color:#1a202c;">{{event_title}}</p>
<table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;">
<tr><td style="padding:4px 0;font-size:14px;color:#4a5568;width:30px;">📅</td>
    <td style="padding:4px 0;font-size:14px;color:#333;">{{event_date_formatted}}</td></tr>
{{time_row}}
{{location_row}}
{{audience_row}}
{{category_row}}
{{cost_row}}
{{registration_row}}
</table>
{{description_block}}
</div>

<table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0;">
<tr>
<td style="background-color:#059669;border-radius:6px;">
<a href="{{approve_url}}" style="display:inline-block;padding:14px 28px;color:#ffffff;text-decoration:none;font-size:16px;font-weight:600;">✅ Approve Event</a>
</td>
<td style="width:12px;"></td>
<td style="background-color:#dc2626;border-radius:6px;">
<a href="{{deny_url}}" style="display:inline-block;padding:14px 28px;color:#ffffff;text-decoration:none;font-size:16px;font-weight:600;">❌ Deny Event</a>
</td>
</tr>
</table>

<p style="font-size:13px;color:#6b7280;line-height:1.5;">These buttons work without logging in. You can also review this event in the <a href="{{portal_review_url}}" style="color:{{color_primary}};">admin portal</a>.</p>

<p style="font-size:15px;color:#333;margin-top:24px;">— CircleBlast Nexus</p>',
];
