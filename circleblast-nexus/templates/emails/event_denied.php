<?php
defined('ABSPATH') || exit;
return [
	'subject' => 'Update on your event submission: {{event_title}}',
	'body'    => '
<p style="font-size:16px;color:#333;margin:0 0 16px;">Hi {{first_name}},</p>
<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
Thanks for submitting <strong>{{event_title}}</strong>. After review, the leadership team has decided not to publish this event at this time.</p>

<p style="font-size:15px;color:#333;line-height:1.6;margin:0 0 16px;">
If you have questions or would like to resubmit with changes, feel free to reach out to any admin — we&rsquo;re happy to help.</p>

<p style="font-size:15px;color:#333;margin-top:24px;">— CircleBlast Nexus</p>',
];
