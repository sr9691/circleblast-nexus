<?php
/**
 * Email Template: Events Digest
 *
 * Sent on a configurable schedule (weekly default) listing upcoming events,
 * or on-demand by an admin for selected events.
 */

defined('ABSPATH') || exit;

return [
	'subject' => 'Upcoming ClubWorks Events',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{first_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 20px;">
{{intro_text}}</p>

{{events_list}}

<p style="font-size:15px;color:#333;line-height:1.6;margin:16px 0;">
Visit the <a href="{{portal_url}}" style="color:#5b2d6e;font-weight:600;">Events page</a> to RSVP and see full details.</p>
<p style="font-size:15px;color:#333;margin:16px 0 0;">— The CircleBlast Team</p>',
];
