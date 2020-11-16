<?php

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

$plugins->add_hook('misc_start', 'postmarathon_misc');
$plugins->add_hook("index_start", "postmarathon_index");
$plugins->add_hook('fetch_wol_activity_end', 'postmarathon_user_activity');
$plugins->add_hook('build_friendly_wol_location_end', 'postmarathon_location_activity');

function postmarathon_info()
{

    global $lang;

    $lang->load("postmarathon");
    return [
        "name"			=> $lang->postmarathon_title,
        "description"	=> $lang->postmarathon_desc,
        "website"		=> "https://github.com/ItsSparksFly",
        "author"		=> "sparks fly & Ales",
        "authorsite"	=> "https://github.com/ItsSparksFly",
        "version"		=> "1.0",
        "guid" 			=> "",
        "codename"		=> "postmarathon",
        "compatibility" => "*"
    ];
}

function postmarathon_install()
{
    global $db, $lang;
    $lang->load("postmarathon");

    if($db->engine=='mysql' || $db->engine=='mysqli' && !$db->table_exists("marathon_users")) {
        
        $db->query("CREATE TABLE `".TABLE_PREFIX."marathon_users` (
            `muid` int(11) NOT NULL AUTO_INCREMENT,  
            `mid` mediumint(9) NOT NULL,  
            `posts` mediumint(9) NOT NULL,  
            `chars` int(15) NOT NULL, 
            `words` int(15) NOT NULL, 
            `uid` varchar(30) CHARACTER SET utf8 NOT NULL,  
            PRIMARY KEY (`muid`)
            ) ENGINE=MyISAM".$db->build_create_table_collation()
        );

        $db->query("CREATE TABLE `".TABLE_PREFIX."marathon_users_data` (
            `mduid` int(11) NOT NULL AUTO_INCREMENT,  
            `mid` mediumint(9) NOT NULL,  
            `posts` mediumint(9) NOT NULL,  
            `chars` int(15) NOT NULL, 
            `words` int(15) NOT NULL, 
            `uid` varchar(30) CHARACTER SET utf8 NOT NULL,  
            PRIMARY KEY (`mduid`)
            ) ENGINE=MyISAM".$db->build_create_table_collation()
        );

        $db->query("CREATE TABLE `".TABLE_PREFIX."marathon` (
            `mid` int(11) NOT NULL AUTO_INCREMENT,  
            `startdate` bigint(20) NOT NULL,  
            `enddate` bigint(20) NOT NULL,  
            PRIMARY KEY (`mid`)
            ) ENGINE=MyISAM".$db->build_create_table_collation());

    }

    // ACP Settings
    $setting_group = [
        'name' => 'postmarathon',
        'title' => $lang->postmarathon_settings_title,
        'description' => $lang->postmarathon_settings_desc,
        'disporder' => 5,
        'isdefault' => 0
    ];

    $gid = $db->insert_query("settinggroups", $setting_group);

    $setting_array = [
        'postmarathon_username' => [
            'title' => $lang->postmarathon_settings_username_title,
            'description' => $lang->postmarathon_settings_username_desc,
            'optionscode' => 'text',
            'value' => '1',
            'disporder' => 1
        ],
        'postmarathon_boards' => [
            'title' => $lang->postmarathon_settings_boards_title,
            'description' => $lang->postmarathon_settings_boards_desc,
            'optionscode' => 'forumselect',
            'disporder' => 2
        ]
    ];

    foreach($setting_array as $name => $setting)
    {
        $setting['name'] = $name;
        $setting['gid'] = $gid;

        $db->insert_query('settings', $setting);
    }

    rebuild_settings();

}

function postmarathon_is_installed()
{
    global $db;
    if(isset($mybb->settings['postmarathon_boards'])) 
    {
        return true;
    }
    return false;
}

function postmarathon_uninstall()
{
    global $db;
    //Settings löschen
    $db->query("DELETE FROM ".TABLE_PREFIX."settinggroups WHERE name='postmarathon'");
    $db->query("DELETE FROM ".TABLE_PREFIX."settings WHERE name='postmarathon_playername'");
    $db->query("DELETE FROM ".TABLE_PREFIX."settings WHERE name='postmarathon_archive'");
    $db->query("DELETE FROM ".TABLE_PREFIX."settings WHERE name='postmarathon_inplay'");
    $db->query("DELETE FROM ".TABLE_PREFIX."settings WHERE name='postmarathon_wantedcount'");
    $db->query("DELETE FROM ".TABLE_PREFIX."settings WHERE name='postmarathon_wanted'");

    //Tabelle löschen
    $db->query("DROP TABLE ".TABLE_PREFIX."marathon_users");
    $db->query("DROP TABLE ".TABLE_PREFIX."marathon_users_data");
    $db->query("DROP TABLE ".TABLE_PREFIX."marathon");

    rebuild_settings();
}

function postmarathon_activate()
{
    global $db;

    $misc_marathon = [
        'title' => 'misc_marathon',
        'template' => $db->escape_string('<html>
    <head>
        <title>{$mybb->settings[\'bbname\']} - {$lang->postmarathon_title}</title>
        {$headerinclude}
    </head>
    <body>
        {$header}
            <table width="100%" cellspacing="5" cellpadding="5">
                <tr>
                    <td valign="top" class="trow1">
                        <div style="padding: 10px; font-size: 12px; text-align: justify;">
                            {$marathon_admin}<br />
                            {$marathon_newuser}
                            <br /><br />
                            <div class="tcat"><b>{$lang->postmarathon_participants} ({$lang->postmarathon_marathon} #{$marathon[\'mid\']} &raquo; <b>{$startdate} - {$enddate})</b></div><br />
                            <table class="tborder" cellpadding="5" cellspacing="5">
                                <tr>
                                    <td class="tcat" align="center">
                                        {$postmarathon_player}
                                    </td>
                                    <td class="tcat" align="center">
                                        {$lang->postmarathon_posts} ({$gesamtpostcount} / {$postcount})
                                    </td>
                                    <td class="tcat" align="center">
                                        {$lang->postmarathon_words} ({$gesamtwordcount} / {$wordcount})
                                    </td>
                                    <td class="tcat" align="center">
                                        {$lang->postmarathon_chars} ({$gesamtcharscount} / {$charcount})
                                    </td>
                                </tr>
                            {$user_bit}
                            </table>
                        </div>
                    </td>
                </tr>
            </table>
    {$footer}
    </body>
</html>'),
        'sid' => '-1',
        'version' => '',
        'dateline' => TIME_NOW
    ];
    $db->insert_query("templates", $misc_marathon);

    $misc_marathon_admin = [
        'title' => 'misc_marathon_admin',
        'template' => $db->escape_string('                {$lang->postmarathon_admin_desc}<div class="tcat"><b>{$lang->postmarathon_date}</b></div><br />      
<form method="post" id="marathon" action="misc.php?action=marathon">
                            <table class="tborder" cellpadding="5" cellspacing="5">
                                <tr>
                                    <td class="tcat" align="center">
                                        {$lang->postmarathon_startdate}
                                    </td>
                                    <td class="tcat" align="center">
                                        {$lang->postmarathon_enddate}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="trow2" align="center">
                                        <input type="date" name="startdate" \>
                                    </td>
                                    <td class="trow2" align="center">
                                        <input type="date"  name="enddate" \>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2" class="trow2" align="center">
                                <input type="hidden" name="action" value="do_marathon_date" />
                                <input type="submit" value="{$lang->postmarathon_submit}" class="button" />
                                    </td>
                                </tr>
                            </table>
                            </form>'),
        'sid' => '-1',
        'version' => '',
        'dateline' => TIME_NOW
    ];
    $db->insert_query("templates", $misc_marathon_admin);

    $misc_marathon_bit = [
        'title' => 'misc_marathon_bit',
        'template' => $db->escape_string('<tr>
    <td class="trow1" align="center">
        {$username}
    </td>
    <td class="trow1" align="center">
        {$inplayposts_count} / {$userposts}
    </td>
    <td class="trow1" align="center">
        {$postswordcount} / {$userwords}
    </td>
    <td class="trow1" align="center">
        {$postscharcount} / {$userchars}
    </td>
</tr>'),
        'sid' => '-1',
        'version' => '',
        'dateline' => TIME_NOW
    ];
    $db->insert_query("templates", $misc_marathon_bit);

    $misc_marathon_newuser = [
        'title' => 'misc_marathon_newuser',
        'template' => $db->escape_string('{$lang->postmarathon_desc}
        <div class="tcat"><b>{$lang->postmarathon_goals}</b></div><br />
                        <form method="post" id="marathon" action="misc.php">
                            <table class="tborder" cellpadding="5" cellspacing="5">
                                <tr>
                                    <td class="tcat" align="center">
                                        {$lang->postmarathon_posts}
                                    </td>
                                    <td class="tcat" align="center">
                                        {$lang->postmarathon_words}
                                    </td>
                                    <td class="tcat" align="center">
                                        {$lang->postmarathon_chars}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="trow2" align="center">
                                        <input type="text" value="{$marathon_goals[\'posts\']}" name="posts" \>
                                    </td>
                                    <td class="trow2" align="center">
                                        <input type="text" value="{$marathon_goals[\'words\']}" name="words" \>
                                    </td>
                                    <td class="trow2" align="center">
                                        <input type="text" value="{$marathon_goals[\'chars\']}" name="chars" \>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="trow2" align="center">
                                <input type="hidden" name="mid" value="{$marathon[\'mid\']}" />
                                <input type="hidden" name="action" value="do_marathon" />
                                <input type="submit" value="{$lang->postmarathon_submit}" class="button" />
                                    </td>
                                </tr>
                            </table>
                            </form>'),
        'sid' => '-1',
        'version' => '',
        'dateline' => TIME_NOW
    ];
    $db->insert_query("templates", $misc_marathon_newuser);

    $misc_marathon_savedata = [
        'title' => "misc_marathon_savedata",
        'template' => $db->escape_string('								<tr>
        <td class="trow2" colspan="4" align="center"><form method="post" id="savemarathon" action="misc.php"><br />{$lang->postmarathon_savedata}<br />
<input type="hidden" name="action" value="do_marathon_savedata" />
<input type="submit" value="{$lang->postmarathon_submit_data}" class="button" />
            </form><br /></td></tr>'),
        'sid' => '-1',
        'version' => '',
        'dateline' => TIME_NOW       
    ];
    $db->insert_query("templates", $misc_marathon_savedata);

    $mindex_boardstats_marathon = [
        'title' => "index_boardstats_marathon",
        'template' => $db->escape_string('<table style="tborder" align="center" cellspacing="5" cellpadding="5" class="smalltext">
        <tr>
            <td colspan="6" class="thead">{$lang->postmarathon_outcome}</td>
        </tr>
        <tr>
            <td class="tcat">{$lang->postmarathon_posts}</td>
            <td class="tcat">{$lang->postmarathon_words}</td>
            <td class="tcat">{$lang->postmarathon_chars}</td>
            <td class="tcat">{$lang->postmarathon_top_player} ({$lang->postmarathon_posts})</td>
            <td class="tcat">{$lang->postmarathon_top_player} ({$lang->postmarathon_words})</td>
            <td class="tcat">{$lang->postmarathon_top_player} ({$lang->postmarathon_chars})</td>
        </tr>
        <tr>
            <td class="trow2" align="center">{$sumposts}</td>
            <td class="trow2" align="center">{$sumwords}</td>
            <td class="trow2" align="center">{$sumchars}</td>
            <td class="trow2" align="center">{$topuserposts}</td>
            <td class="trow2" align="center">{$topuserwords}</td>
            <td class="trow2" align="center">{$topuserchars}</td>
        </tr>
    </table>'),
        'sid' => '-1',
        'version' => '',
        'dateline' => TIME_NOW       
    ];
    $db->insert_query("templates", $index_boardstats_marathon);

}

function postmarathon_deactivate()
{
    global $db;
    $db->delete_query("templates", "title LIKE '%misc_marathon%'");
}

function postmarathon_index() {
    global $db, $templates, $lang, $index_marathon, $mybb;
    $lang->load('postmarathon');

    $ufid = $mybb->settings['postmarathon_username'];
    $mid = $db->fetch_field($db->query("SELECT mid FROM ".TABLE_PREFIX."marathon ORDER BY mid DESC LIMIT 1"), "mid");
    $sumposts = $db->fetch_field($db->query("SELECT sum(posts) AS sum_posts FROM ".TABLE_PREFIX."marathon_users_data WHERE mid = '$mid'"), "sum_posts");
    $sumwords = $db->fetch_field($db->query("SELECT sum(words) AS sum_words FROM ".TABLE_PREFIX."marathon_users_data WHERE mid = '$mid'"), "sum_words");
    $sumchars = $db->fetch_field($db->query("SELECT sum(chars) AS sum_chars FROM ".TABLE_PREFIX."marathon_users_data WHERE mid = '$mid'"), "sum_chars");

    $topuserposts = $db->fetch_field($db->query("
    SELECT fid".$ufid." FROM ".TABLE_PREFIX."marathon_users_data ud
    LEFT JOIN ".TABLE_PREFIX."userfields uf
    ON ud.uid = uf.ufid
    WHERE posts = (SELECT MAX(posts) FROM ".TABLE_PREFIX."marathon_users_data WHERE mid = '$mid')
    "), "fid".$ufid);

    $topuserwords = $db->fetch_field($db->query("
    SELECT fid".$ufid." FROM ".TABLE_PREFIX."marathon_users_data ud
    LEFT JOIN ".TABLE_PREFIX."userfields uf
    ON ud.uid = uf.ufid
    WHERE words = (SELECT MAX(words) FROM ".TABLE_PREFIX."marathon_users_data WHERE mid = '$mid')
    "), "fid".$ufid);

    $topuserchars = $db->fetch_field($db->query("
    SELECT fid".$ufid." FROM ".TABLE_PREFIX."marathon_users_data ud
    LEFT JOIN ".TABLE_PREFIX."userfields uf
    ON ud.uid = uf.ufid
    WHERE chars = (SELECT MAX(chars) FROM ".TABLE_PREFIX."marathon_users_data WHERE mid = '$mid')
    "), "fid".$ufid);

    eval("\$index_marathon = \"".$templates->get("index_boardstats_marathon")."\";");
}

function postmarathon_misc() {
    global $mybb, $templates, $lang, $header, $headerinclude, $footer, $page, $db, $lang;
    $lang->load("postmarathon");

    if ($mybb->input['action'] == "marathon") {

        // account switcher
        $userid = intval($mybb->user['as_uid']);
        if($userid == 0) {
            $userid = intval($mybb->user['uid']);
        }

        // get marathon settings
        $ufid = $mybb->settings['postmarathon_username'];
        $boards = $mybb->settings['postmarathon_boards'];

        // get latest marathon
        $query = $db->query("
            SELECT * FROM ".TABLE_PREFIX."marathon
            ORDER BY mid DESC
            LIMIT 1
        ");
        $marathon = $db->fetch_array($query);

        // check for active marathon
        $check_active = $db->fetch_field($db->query("SELECT mduid FROM ".TABLE_PREFIX."marathon_users_data WHERE mid = '{$marathon['mid']} LIMIT 1'"), "mduid");
        // check if user is admin
        if($mybb->usergroup['cancp'] == 1) {
            if($check_active) {
                eval("\$marathon_admin = \"".$templates->get("misc_marathon_admin")."\";");
            } else {
                eval("\$marathon_savedata = \"".$templates->get("misc_marathon_savedata")."\";");
            }
        }
        if(!$check_active) {
            if($mybb->user['uid'] != 0) {
                eval("\$marathon_newuser = \"".$templates->get("misc_marathon_newuser")."\";");
            }
        }
        else {
            $marathon_newuser = "<center>Aktuell findet im <em>".$mybb->settings['bbname']."</em> kein Postingmarathon statt. <br /> Es werden die Ergebnisse vom letzten Marathon dargestellt.</center>";
        }

        // format marathon dates
        $startdate = date('d.m.Y', $marathon['startdate']);
        $enddate = date('d.m.Y', $marathon['enddate']);

        // get goals to latest marathon
        $query = $db->query("
            SELECT * FROM ".TABLE_PREFIX."marathon_users
            WHERE uid = '{$userid}'
            AND mid = '{$marathon['mid']}'
        ");
        $marathon_goals = $db->fetch_array($query);


        // get user's marathon data
        $query = $db->query("SELECT * FROM " . TABLE_PREFIX . "marathon_users u
            LEFT JOIN ".TABLE_PREFIX."userfields uf
            on (u.uid = uf.ufid)
            WHERE mid  = '{$marathon['mid']}'
		");
        
        // set up (some) counters
        $gesamtpostcount = 0;
        $postcharcount = 0;
        $postwordcount = 0;
        $gesamtcharscount = 0;
        $gesamtwordcount = 0;

        // since we may have multiple boards to consider, let's set up our boardlist
        $boardlist = explode(",", $boards);

        while($marathon_user = $db->fetch_array($query)) {
            // set up variables
            $postscharcount = 0;
            $postswordcount = 0;
            $uid = $marathon_user['uid'];

			$username = $marathon_user['fid'.$ufid];
			
            if ($marathon_user['posts'] == "0") {
                $userposts = $lang->postmarathon_empty_goal;
            }
            if ($marathon_user['chars'] == "0") {
                $userchars = $lang->postmarathon_empty_goal;
            }
            if ($marathon_user['words'] == "0") {
                $userwords = $lang->postmarathon_empty_goal;
            }

            // count all posts by every user in given date span
            $query_2 = $db->query("
                SELECT * FROM ".TABLE_PREFIX."posts p
                LEFT JOIN ".TABLE_PREFIX."threads t
                ON p.tid = t.tid
                LEFT JOIN ".TABLE_PREFIX."forums f
                ON t.fid = f.fid
                LEFT JOIN ".TABLE_PREFIX."users u
                ON p.uid = u.uid
                WHERE p.dateline BETWEEN '{$marathon['startdate']}' AND '{$marathon['enddate']}'
                AND (u.as_uid = '$uid' OR u.uid = '$uid')
            ");

            // count numbers up
            while ($inplaypost = $db->fetch_array($query_2)) {
                // but only if we're on selected forums...
                foreach($boardlist as $board) {
                    $inplaypost['parentlist'] = ",".$inplaypost['parentlist'].",";
                    if(preg_match("/,$board,/i", $inplaypost['parentlist'])) {
                        $postcharcount = strlen(strip_tags($inplaypost['message']));
                        $postscharcount += $postcharcount;
                        $searchexp = array("\"", "”", "„", "-", "_", "<", ">", "/", "–", "[", "]");
                        $postwordcount = count(explode(' ', preg_replace('/\s+/', ' ', str_ireplace($searchexp, '', trim($inplaypost['message']))))); 
                        $postswordcount += $postwordcount;
                    }
                }
            }
            $inplayposts_count = mysqli_num_rows($query_2);
            $gesamtpostcount += $inplayposts_count;
            $gesamtcharscount += $postscharcount;
            $gesamtwordcount += $postswordcount;
			

            if($marathon_user['posts'] != 0) {
                $userposts = number_format($marathon_user['posts'], '0', ',', '.');
            }
            if($marathon_user['words'] != 0) {
                $userwords  = number_format($marathon_user['words'], '0', ',', '.');
            }

            if($marathon_user['chars'] != 0) {
                $userchars = number_format($marathon_user['chars'], '0', ',', '.');
            }

            eval("\$user_bit .= \"" . $templates->get("misc_marathon_bit") . "\";");
        }
		
        // style numbers
        $gesamtpostcount = number_format($gesamtpostcount, '0', ',', '.');
        $gesamtcharscount = number_format($gesamtcharscount, '0', ',', '.');
        $gesamtwordcount = number_format($gesamtwordcount, '0', ',', '.');
        $inplayposts_count = number_format($inplayposts_count, '0', ',', '.');
        $postswordcount = number_format($postswordcount, '0', ',', '.');
         $postscharcount =  number_format($postscharcount, '0', ',', '.');

        $charcount = number_format($db->fetch_field($db->query("SELECT sum(chars) AS char_sum FROM ".TABLE_PREFIX."marathon_users WHERE mid = '{$marathon['mid']}'"), "char_sum"), '0', ',', '.');
        $postcount = number_format($db->fetch_field($db->query("SELECT sum(posts) AS post_sum FROM ".TABLE_PREFIX."marathon_users WHERE mid = '{$marathon['mid']}'"), "post_sum"), '0', ',', '.');
        $wordcount = number_format($db->fetch_field($db->query("SELECT sum(words) AS word_sum FROM ".TABLE_PREFIX."marathon_users WHERE mid = '{$marathon['mid']}'"), "word_sum"), '0', ',', '.');

        eval("\$page .= \"".$templates->get("misc_marathon")."\";");
        output_page($page);
    }

    if($mybb->input['action'] == "do_marathon_savedata") {

        $query = $db->query("
            SELECT * FROM ".TABLE_PREFIX."marathon
            ORDER BY mid DESC
            LIMIT 1
        ");
        $marathon = $db->fetch_array($query);

        // get user's marathon data
        $query = $db->query("
            SELECT * FROM ".TABLE_PREFIX."marathon_users
            WHERE mid  = '{$marathon['mid']}'
        ");

        // since we may have multiple boards to consider, let's set up our query
		$boards = $mybb->settings['postmarathon_boards'];
        $boardlist = explode(",", $boards);
		
        while($marathon_user = $db->fetch_array($query)) {
            $postscharcount = 0;
            $postswordcount = 0;
            $uid = $marathon_user['uid'];
			

            // count all posts by every user in given date span
            $query_2 = $db->query("
                SELECT * FROM ".TABLE_PREFIX."posts p
                LEFT JOIN ".TABLE_PREFIX."threads t
                ON p.tid = t.tid
                LEFT JOIN ".TABLE_PREFIX."forums f
                ON t.fid = f.fid
                LEFT JOIN ".TABLE_PREFIX."users u
                ON p.uid = u.uid
                WHERE p.dateline BETWEEN '{$marathon['startdate']}' AND '{$marathon['enddate']}'
                AND (u.as_uid = '$uid' OR u.uid = '$uid')
            ");			

            while ($inplaypost = $db->fetch_array($query_2)) {
                foreach($boardlist as $board) {
                    $inplaypost['parentlist'] = ",".$inplaypost['parentlist'].",";
                    if(preg_match("/,$board,/i", $inplaypost['parentlist'])) {
                        $postcharcount = strlen(strip_tags($inplaypost['message']));
                        $postscharcount += $postcharcount;
                        $searchexp = array("\"", "”", "„", "-", "_", "<", ">", "/", "–", "[", "]");
                        $postwordcount = count(explode(' ', preg_replace('/\s+/', ' ', str_ireplace($searchexp, '', trim($inplaypost['message']))))); 
                        $postswordcount += $postwordcount;
                    }
                }
            }
            $inplayposts_count = mysqli_num_rows($query_2);

            $insert_array = [
				"mid" => $marathon['mid'],
                "uid" => $uid,
                "chars" => $postscharcount,
                "words" => $postswordcount,
                "posts" => $inplayposts_count
            ];
            $db->insert_query("marathon_users_data", $insert_array);
        }   
        redirect("misc.php?action=marathon");
    }

    if($mybb->input['action'] == "do_marathon_date") {

        // insert date into database
        $startdate = $mybb->get_input('startdate');
        $enddate = $mybb->get_input('enddate');

        $startdate = strtotime($startdate. " 0:00");
        $enddate = strtotime($enddate. " 24:00");

        $input_array = [
            "startdate" => $startdate,
            "enddate" => $enddate,
        ];
        $db->insert_query("marathon", $input_array);

        redirect("misc.php?action=marathon");
    }

    if ($mybb->input['action'] == "do_marathon") {

        // account switcher
        $userid = intval($mybb->user['as_uid']);
        if($userid == 0){
            $userid = $mybb->user['uid'];
        }

        $uid = $userid;
        $chars = (int)$mybb->get_input('chars');
        $posts = (int)$mybb->get_input('posts');
        $words = (int)$mybb->get_input('words');
        $mid = (int)$mybb->get_input('mid');

        if(empty($chars)){
            $chars = 0;
        }

        $check = $db->fetch_field($db->query("SELECT muid FROM " . TABLE_PREFIX . "marathon_users WHERE uid = '$uid' AND mid = '$mid'"), "muid");
        if (!empty($check)) {
            $db->delete_query("marathon_users", "muid = '$check'");
        }

        $input_array = [
            "posts" => $posts,
            "chars" => $chars,
            "words" => $words,
            "uid" => $uid,
            "mid" => $mid
        ];

        $db->insert_query("marathon_users", $input_array);
        redirect("misc.php?action=marathon");
    }

}

// who's online
function postmarathon_user_activity($user_activity) {
    global $user;

    if(my_strpos($user['location'], "misc.php?action=marathon") !== false) {
        $user_activity['activity'] = "marathon";
    }

    return $user_activity;
}

function postmarathon_location_activity($plugin_array) {
    global $db, $mybb, $lang;

    if($plugin_array['user_activity']['activity'] == "marathon")
    {
        $plugin_array['location_name'] = "Betrachtet die <b><a href='misc.php?action=marathon'>Postmarathonübersicht</a></b>.";
    }

    return $plugin_array;
}