<?php
/**
 * Color Scheme Service
 *
 * Manages the portal's color scheme. Stores the active scheme in wp_options,
 * provides preset palettes, and generates CSS variable overrides + email colors.
 *
 * Default scheme ("circleblast") is derived from the CB logo: steel blue + amber orange.
 */

defined('ABSPATH') || exit;

final class CBNexus_Color_Scheme {

	const OPTION_KEY = 'cbnexus_color_scheme';

	/**
	 * Preset color schemes.
	 *
	 * Each preset defines the "seed" colors; derived shades are computed in get_full_palette().
	 * Keys: accent (primary), secondary (gold/accent2), green, blue, red.
	 */
	const PRESETS = [
		'circleblast' => [
			'label'     => 'CircleBlast (Default)',
			'accent'    => '#2b5a94',  // Steel blue from logo "C"
			'secondary' => '#f09214',  // Amber orange from logo "B"
			'green'     => '#3d8b4d',
			'blue'      => '#2b5a94',
			'red'       => '#c44040',
			'bg'        => '#f0f4f8',
			'text'      => '#1e2a3a',
		],
		'plum_gold' => [
			'label'     => 'Plum & Gold',
			'accent'    => '#5b2d6e',
			'secondary' => '#c49a3c',
			'green'     => '#4a7a56',
			'blue'      => '#4a6f8a',
			'red'       => '#9e4444',
			'bg'        => '#f3eef6',
			'text'      => '#2a1f33',
		],
		'ocean' => [
			'label'     => 'Ocean Teal',
			'accent'    => '#0d7377',
			'secondary' => '#e8a838',
			'green'     => '#2e8b57',
			'blue'      => '#2563eb',
			'red'       => '#dc4444',
			'bg'        => '#f0f7f7',
			'text'      => '#1a2e2e',
		],
		'slate' => [
			'label'     => 'Slate Professional',
			'accent'    => '#334155',
			'secondary' => '#ea580c',
			'green'     => '#16a34a',
			'blue'      => '#2563eb',
			'red'       => '#dc2626',
			'bg'        => '#f1f5f9',
			'text'      => '#0f172a',
		],
		'forest' => [
			'label'     => 'Forest & Copper',
			'accent'    => '#1b5e3b',
			'secondary' => '#c67b30',
			'green'     => '#1b5e3b',
			'blue'      => '#3b6fa0',
			'red'       => '#b33a3a',
			'bg'        => '#f2f6f3',
			'text'      => '#1a2e1e',
		],
		'custom' => [
			'label'     => 'Custom',
			'accent'    => '#2b5a94',
			'secondary' => '#f09214',
			'green'     => '#3d8b4d',
			'blue'      => '#2b5a94',
			'red'       => '#c44040',
			'bg'        => '#f0f4f8',
			'text'      => '#1e2a3a',
		],
	];

	/**
	 * Get the saved scheme config.
	 */
	public static function get_scheme(): array {
		$saved = get_option(self::OPTION_KEY, []);
		if (empty($saved) || !isset($saved['preset'])) {
			return array_merge(['preset' => 'circleblast'], self::PRESETS['circleblast']);
		}
		return $saved;
	}

	/**
	 * Save a scheme config.
	 */
	public static function save_scheme(array $data): void {
		update_option(self::OPTION_KEY, $data, false);
	}

	/**
	 * Get the full CSS variable palette derived from the active scheme.
	 */
	public static function get_full_palette(): array {
		$s = self::get_scheme();
		$accent    = $s['accent']    ?? '#2b5a94';
		$secondary = $s['secondary'] ?? '#f09214';
		$green     = $s['green']     ?? '#3d8b4d';
		$blue      = $s['blue']      ?? '#2b5a94';
		$red       = $s['red']       ?? '#c44040';
		$bg        = $s['bg']        ?? '#f0f4f8';
		$text      = $s['text']      ?? '#1e2a3a';

		return [
			'--cb-bg'            => $bg,
			'--cb-bg-deep'       => self::adjust_brightness($bg, -5),
			'--cb-card'          => '#ffffff',
			'--cb-card-hover'    => '#fdfcfe',
			'--cb-border'        => self::mix($bg, $text, 0.2),
			'--cb-border-soft'   => self::mix($bg, $text, 0.1),
			'--cb-text'          => $text,
			'--cb-text-sec'      => self::mix($text, '#888888', 0.5),
			'--cb-text-ter'      => self::mix($text, '#aaaaaa', 0.55),
			'--cb-accent'        => $accent,
			'--cb-accent-soft'   => self::tint($accent, 0.9),
			'--cb-accent-hover'  => self::adjust_brightness($accent, -12),
			'--cb-accent-mid'    => self::tint($accent, 0.35),
			'--cb-gold'          => $secondary,
			'--cb-gold-soft'     => self::tint($secondary, 0.9),
			'--cb-gold-border'   => self::tint($secondary, 0.6),
			'--cb-gold-deep'     => self::adjust_brightness($secondary, -15),
			'--cb-green'         => $green,
			'--cb-green-soft'    => self::tint($green, 0.9),
			'--cb-green-border'  => self::tint($green, 0.6),
			'--cb-blue'          => $blue,
			'--cb-blue-soft'     => self::tint($blue, 0.9),
			'--cb-red'           => $red,
			'--cb-red-soft'      => self::tint($red, 0.9),
		];
	}

	/**
	 * Generate inline CSS string for :root overrides.
	 */
	public static function get_css_overrides(): string {
		$vars = self::get_full_palette();
		$lines = [];
		foreach ($vars as $prop => $val) {
			$lines[] = $prop . ':' . $val;
		}
		return ':root{' . implode(';', $lines) . '}';
	}

	/**
	 * Get colors specifically for email templates.
	 */
	public static function get_email_colors(): array {
		$s = self::get_scheme();
		return [
			'header_bg'    => $s['accent']    ?? '#2b5a94',
			'btn_primary'  => $s['accent']    ?? '#2b5a94',
			'btn_secondary'=> $s['secondary'] ?? '#f09214',
			'accent_text'  => $s['accent']    ?? '#2b5a94',
			'secondary'    => $s['secondary'] ?? '#f09214',
			'green'        => $s['green']     ?? '#3d8b4d',
			'blue'         => $s['blue']      ?? '#2b5a94',
			'bg'           => '#f5f5f5',
			'footer_bg'    => '#f8f9fa',
		];
	}

	/**
	 * Get the URL to the logo image.
	 */
	public static function get_logo_url(string $size = 'full'): string {
		$file = match ($size) {
			'email' => 'logo-email.png',
			'small' => 'logo-small.png',
			default => 'logo.png',
		};
		return CBNEXUS_PLUGIN_URL . 'assets/img/' . $file;
	}

	// ─── Color Math Helpers ─────────────────────────────────────────────

	/**
	 * Parse hex to [r, g, b].
	 */
	private static function hex_to_rgb(string $hex): array {
		$hex = ltrim($hex, '#');
		if (strlen($hex) === 3) {
			$hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
		}
		return [
			hexdec(substr($hex, 0, 2)),
			hexdec(substr($hex, 2, 2)),
			hexdec(substr($hex, 4, 2)),
		];
	}

	/**
	 * Convert RGB to hex.
	 */
	private static function rgb_to_hex(int $r, int $g, int $b): string {
		return '#' . str_pad(dechex(max(0, min(255, $r))), 2, '0', STR_PAD_LEFT)
		           . str_pad(dechex(max(0, min(255, $g))), 2, '0', STR_PAD_LEFT)
		           . str_pad(dechex(max(0, min(255, $b))), 2, '0', STR_PAD_LEFT);
	}

	/**
	 * Tint a color towards white by a ratio (0 = original, 1 = white).
	 */
	private static function tint(string $hex, float $ratio): string {
		[$r, $g, $b] = self::hex_to_rgb($hex);
		return self::rgb_to_hex(
			(int) round($r + (255 - $r) * $ratio),
			(int) round($g + (255 - $g) * $ratio),
			(int) round($b + (255 - $b) * $ratio)
		);
	}

	/**
	 * Adjust brightness by a percentage (-100 to +100).
	 */
	private static function adjust_brightness(string $hex, int $pct): string {
		[$r, $g, $b] = self::hex_to_rgb($hex);
		$factor = 1 + ($pct / 100);
		return self::rgb_to_hex(
			(int) round($r * $factor),
			(int) round($g * $factor),
			(int) round($b * $factor)
		);
	}

	/**
	 * Mix two colors by a ratio (0 = color1, 1 = color2).
	 */
	private static function mix(string $hex1, string $hex2, float $ratio): string {
		[$r1, $g1, $b1] = self::hex_to_rgb($hex1);
		[$r2, $g2, $b2] = self::hex_to_rgb($hex2);
		return self::rgb_to_hex(
			(int) round($r1 + ($r2 - $r1) * $ratio),
			(int) round($g1 + ($g2 - $g1) * $ratio),
			(int) round($b1 + ($b2 - $b1) * $ratio)
		);
	}
}
