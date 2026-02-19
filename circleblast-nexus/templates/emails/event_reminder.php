<?php
defined('ABSPATH') || exit;
return [
	'subject' => 'Reminder: {{event_title}} is tomorrow!',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{first_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
Just a reminder — <strong>{{event_title}}</strong> is coming up tomorrow!</p>
<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin:16px 0;">
<p style="margin:0 0 4px;font-size:15px;"><strong>📅 {{event_date}}</strong></p>
<p style="margin:0 0 4px;font-size:14px;color:#4a5568;">🕐 {{event_time}}</p>
<p style="margin:0;font-size:14px;color:#4a5568;">📍 {{event_location}}</p>
{{cost_block}}
</div>
{{reminder_notes_block}}
{{registration_block}}
<p style="font-size:14px;color:#4a5568;line-height:1.6;">{{description}}</p>
<p style="font-size:15px;color:#333;margin:16px 0 0;">— The CircleBlast Team</p>',
];
