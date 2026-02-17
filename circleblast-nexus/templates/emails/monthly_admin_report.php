<?php
defined('ABSPATH') || exit;
return [
	'subject' => 'CircleBlast Monthly Report — {{total_members}} Members, {{total_meetings}} Meetings',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{first_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">Here\'s your monthly CircleBlast admin report.</p>

<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:20px;margin:16px 0;">
<table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;">
<tr>
<td style="text-align:center;padding:8px;">
<div style="font-size:28px;font-weight:700;color:#2563eb;">{{total_members}}</div>
<div style="font-size:13px;color:#718096;">Active Members</div>
</td>
<td style="text-align:center;padding:8px;">
<div style="font-size:28px;font-weight:700;color:#059669;">{{total_meetings}}</div>
<div style="font-size:13px;color:#718096;">Completed Meetings</div>
</td>
<td style="text-align:center;padding:8px;">
<div style="font-size:28px;font-weight:700;color:#7c3aed;">{{accept_rate}}</div>
<div style="font-size:13px;color:#718096;">Acceptance Rate</div>
</td>
<td style="text-align:center;padding:8px;">
<div style="font-size:28px;font-weight:700;color:#e53e3e;">{{high_risk_count}}</div>
<div style="font-size:13px;color:#718096;">High-Risk Members</div>
</td>
</tr>
</table>
</div>

<table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0;">
<tr><td style="background-color:#2563eb;border-radius:6px;">
<a href="{{portal_url}}" style="display:inline-block;padding:14px 28px;color:#ffffff;text-decoration:none;font-size:16px;font-weight:600;">View Full Analytics</a>
</td></tr></table>
<p style="font-size:15px;color:#333;">— CircleBlast Nexus</p>',
];
