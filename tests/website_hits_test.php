<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../_Helper/Database.php';
require_once __DIR__ . '/../_Helper/WebsiteHits.php';
$pdo = Database::getConnection();
preg_match('/CREATE TABLE IF NOT EXISTS `website_hits` .*?;/s', file_get_contents(__DIR__ . '/../DB/web_sekolah.sql'), $match);
$pdo->exec(str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', $match[0]));
$server = ['REMOTE_ADDR'=>'127.0.0.1','HTTP_USER_AGENT'=>'Mozilla/5.0','HTTPS'=>'on'];
WebsiteHits::record($pdo,$server,[],'/index.php/Beranda','Beranda','/');
$first = $pdo->query('SELECT *, INET6_NTOA(ip_address) AS ip FROM website_hits')->fetch();
$cookies = ['school_visitor'=>$first['visitor_id'],'school_visit_session'=>$first['session_id']];
WebsiteHits::record($pdo,$server,$cookies,'/index.php/Guru','Guru','/');
$server['REMOTE_ADDR']='2001:db8::1';
$server['HTTP_USER_AGENT']='Googlebot';
$server['HTTP_REFERER']='https://example.com/';
WebsiteHits::record($pdo,$server,['school_visitor'=>$first['visitor_id']],'/index.php/Guru','Guru','/');
$server['REMOTE_ADDR']='invalid';
$server['HTTP_X_FORWARDED_FOR']='8.8.8.8';
$server['HTTP_USER_AGENT']="bad\xff";
$server['HTTP_REFERER']=str_repeat('x',3000);
WebsiteHits::record($pdo,$server,['school_visitor'=>'invalid','school_visit_session'=>[]],str_repeat('a',3000),str_repeat("\xc3\xa9",300),'/');
$rows = $pdo->query('SELECT *, INET6_NTOA(ip_address) AS ip, CHAR_LENGTH(page_path) AS path_length, CHAR_LENGTH(page_title) AS title_length FROM website_hits ORDER BY id_hit')->fetchAll();
$checks = [
    'four page views'=>count($rows)===4,
    'visitor uuid'=>strlen($first['visitor_id'])===36 && $first['visitor_id']!==$first['session_id'],
    'visitor and session reused'=>$rows[1]['visitor_id']===$first['visitor_id'] && $rows[1]['session_id']===$first['session_id'],
    'new session retains visitor'=>$rows[2]['visitor_id']===$first['visitor_id'] && $rows[2]['session_id']!==$first['session_id'],
    'IPv4 and IPv6'=>$first['ip']==='127.0.0.1' && $rows[2]['ip']==='2001:db8::1',
    'bot flag'=>$first['is_bot']===0 && $rows[2]['is_bot']===1,
    'untrusted forwarding ignored'=>$rows[3]['ip_address']===null,
    'limits and UTF8'=>$rows[3]['path_length']===2048 && $rows[3]['title_length']===255 && strlen($rows[3]['referrer_url'])===2048 && preg_match('//u',$rows[3]['user_agent'])===1,
    'invalid cookies replaced'=>strlen($rows[3]['visitor_id'])===36 && strlen($rows[3]['session_id'])===36,
];
foreach ($checks as $name=>$ok) echo ($ok?'PASS ':'FAIL ') . $name . PHP_EOL;
exit(in_array(false,$checks,true) ? 1 : 0);
