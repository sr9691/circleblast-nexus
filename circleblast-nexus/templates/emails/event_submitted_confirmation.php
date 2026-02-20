<?php
defined('ABSPATH') || exit;
return [
	'subject' => 'Your event has been submitted: {{event_title}}',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{first_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
Thanks for submitting an event! It&rsquo;s now pending review by the CircleBlast leadership team.</p>

<div style="background:#f8fafc;border:1px solid #e2e8f0;border-left:4px solid {{color_primary}};border-radius:8px;padding:20px;margin:16px 0;">
<p style="margin:0 0 4px;font-size:17px;font-weight:700;color:#1a202c;">{{event_title}}</p>
<p style="margin:0;font-size:14px;color:#4a5568;">📅 {{event_date_formatted}}</p>
{{time_line}}
{{location_line}}
</div>

<p style="font-size:15px;color:#333;line-height:1.6;">
You&rsquo;ll receive an email once your event has been approved. If any changes are needed, an admin will reach out.</p>

<p style="font-size:15px;color:#333;margin-top:24px;">— CircleBlast Nexus</p>',
];
