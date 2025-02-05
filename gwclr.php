<?php

const BOT_TOKEN = "aaaa";
const STORAGE_PATH = __DIR__ . "/storage";
const CHAT_ID_REMINDER_2030 = "-123";
const CHAT_ID_ENGLISH_DAY = "-123";

if (!is_dir(STORAGE_PATH)) {
	mkdir(STORAGE_PATH, 0777, true);
	if (!is_dir(STORAGE_PATH))
		throw new Exception("Failed to create storage directory at " . STORAGE_PATH);
}

$CURL = curl_init();

class Exe
{
	public static function exec_curl($ch, $url, $opt = [])
	{
		$def = [
			CURLOPT_URL		=> $url,
			CURLOPT_RETURNTRANSFER	=> true
		];

		foreach ($opt as $k => $v)
			$def[$k] = $v;

		curl_setopt_array($ch, $def);
		$r = curl_exec($ch);
		if ($r === false)
			throw new Exception("Curl error: " . curl_error($ch));

		return $r;
	}

	public static function __callStatic($name, $args)
	{
		global $CURL;

		$url = "https://api.telegram.org/bot" . BOT_TOKEN . "/{$name}";
		$payload = $args[0] ?? [];
		$extra_opt = $args[1] ?? [];

		$o = [
			CURLOPT_POST		=> true,
			CURLOPT_POSTFIELDS	=> http_build_query($payload)
		];

		$r = self::exec_curl($CURL, $url, $o);
		$j = json_decode($r, true);

		if ($j["ok"] !== true)
			throw new Exception("Telegram API error: " . $j["description"]);

		return $j;
	}
};

const ENGLSIH_DAY_ANNOUNCEMENT = <<<EOD
From: GNU/Weeb Maid Bot &lt;maid@vger.gnuweeb.org&gt;
Telegram-cc: @moepoi @lappv @ammarfaizi2 @kanameless
Date: {{date}}
Subject: [ANNOUNCEMENT] English day

Hi,

Today is the English day for GNU/Weeb, please use English in today
conversation.

English day for GNU/Weeb is Sunday, Jakarta time.

-- 
GNU/Weeb Maid Bot
<code>------------------------------------------------------------------------</code>
EOD;

function __english_day()
{
	$day = date("w");
	$chat = Exe::getChat(["chat_id" => CHAT_ID_ENGLISH_DAY]);
	$t = $chat["result"]["title"];

	if (00 && $day != "7") {
		printf("english_day: Today is not Sunday.\n");

		// Check whether the group name contains "English Day".
		if (stripos($t, "english day") === false) {
			printf("english_day: The group name does not contain 'English Day'.\n");
			return;
		}

		// Clear the group name.
		$p = [
			"chat_id" => CHAT_ID_ENGLISH_DAY,
			"title" => trim(preg_replace("/\[english day\]/i", "", $t))
		];

		try {
			Exe::setChatTitle($p);
			printf("english_day: Group name cleared.\n");
		} catch (Exception $e) {
			printf("english_day: Failed to clear group name: %s\n", $e->getMessage());
		}
	} else {
		printf("english_day: Today is Sunday (English Day).\n");

		// Check whether the group name contains "English Day".
		if (stripos($t, "english day") !== false) {
			printf("english_day: The group name already contains 'English Day'.\n");
			return;
		}

		// Set the group name.
		$p = [
			"chat_id" => CHAT_ID_ENGLISH_DAY,
			"title" => "[English Day] {$t}"
		];

		try {
			$msg = str_replace("{{date}}", date("r"), ENGLSIH_DAY_ANNOUNCEMENT);
			Exe::setChatTitle($p);
			Exe::sendMessage([
				"chat_id"	=> CHAT_ID_ENGLISH_DAY,
				"text"		=> $msg,
				"parse_mode"	=> "HTML"
			]);
			printf("english_day: Group name set.\n");
		} catch (Exception $e) {
			printf("english_day: Failed to set group name: %s\n", $e->getMessage());
		}
	}
}

function english_day()
{
	$day_hash = date("Y-m-d");
	$memfile = STORAGE_PATH . "/english_day.txt";

	if (file_exists($memfile)) {
		$m = file_get_contents($memfile);
		if ($m == $day_hash) {
			printf("english_day: English Day has been checked today.\n");
			return;
		}
	}

	__english_day();
	file_put_contents($memfile, $day_hash);
}

function reminder_2030()
{
	/*
	 * Check if the reminder has been sent today.
	 */
	$memfile = STORAGE_PATH . "/reminder_2030.txt";
	$now = time();
	if (file_exists($memfile)) {
		$m = file_get_contents($memfile);
		$p = date("Y-m-d", $now) . " 00:00:00";

		if ($m >= $p) {
			printf("reminder_2030: Reminder has been sent today.\n");
			return;
		}
	}

	/*
	 * Calculate the remaining days.
	 */
	$target_date = strtotime("2030-01-01 00:00:00");
	$ryears = floor(($target_date - $now) / 31536000);
	$rdays = floor(($target_date - $now) / 86400);

	/*
	 * Send the reminder.
	 */
	$ryd = $ryears * 365;
	$m = sprintf(
		    "#reminder\n\n".
		    "Today is %s.\n\n".
		    "There are %d days left until %s.\n\n".
		    "[ %d year%s + %d day%s. ]",
		     date("l, d F Y", $now),
		     $rdays, date("l, d F Y", $target_date),
		     $ryears, $ryears > 1 ? "s" : "", $rdays - $ryd, $rdays - $ryd > 1 ? "s" : "");

	try {
		Exe::sendMessage([
			"chat_id"	=> CHAT_ID_REMINDER_2030,
			"text"		=> $m
		]);
		printf("reminder_2030: Reminder sent.\n");
	} catch (Exception $e) {
		printf("reminder_2030: Failed to send reminder: %s\n", $e->getMessage());
		return;
	}

	/*
	 * Update the reminder sent date.
	 */
	file_put_contents($memfile, date("Y-m-d", $now)." 00:00:00");
}

english_day();
reminder_2030();
