<?php
defined('ABSPATH') || exit;
return [
	'subject' => 'CircleUp Recap: {{meeting_title}}',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{first_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
Here\'s your recap from <strong>{{meeting_title}}</strong> ({{meeting_date}}).</p>

<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:20px;margin:16px 0;">
<table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;">
<tr>
<td style="text-align:center;padding:8px;">
<div style="font-size:28px;font-weight:700;color:#2563eb;">{{wins_count}}</div>
<div style="font-size:13px;color:#718096;">Wins</div>
</td>
<td style="text-align:center;padding:8px;">
<div style="font-size:28px;font-weight:700;color:#7c3aed;">{{insights_count}}</div>
<div style="font-size:13px;color:#718096;">Insights</div>
</td>
<td style="text-align:center;padding:8px;">
<div style="font-size:28px;font-weight:700;color:#059669;">{{actions_count}}</div>
<div style="font-size:13px;color:#718096;">Actions</div>
</td>
</tr>
</table>
</div>

<div style="font-size:15px;color:#333;line-height:1.6;margin:16px 0;">{{summary}}</div>

<table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0;">
<tr><td style="background-color:#2563eb;border-radius:6px;">
<a href="{{portal_url}}" style="display:inline-block;padding:14px 28px;color:#ffffff;text-decoration:none;font-size:16px;font-weight:600;">View Full Details</a>
</td></tr></table>
<p style="font-size:15px;color:#333;">— The CircleBlast Team</p>',
];
