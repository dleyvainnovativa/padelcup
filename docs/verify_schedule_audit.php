<?php
require __DIR__ . '/../app/Services/Tournament/ScheduleAudit.php';
use App\Services\Tournament\ScheduleAudit;

$tz = new DateTimeZone('America/Mexico_City');
function t($s){ global $tz; return (new DateTimeImmutable($s,$tz))->getTimestamp(); }
function pl(...$names){ $o=[]; foreach($names as $n){$o[strtolower($n)]=$n;} return $o; }
function row($id,$cat,$o){
  $d = ['id'=>$id,'category_id'=>$cat,'category'=>"Cat$cat",'group_id'=>null,'group_letter'=>null,'round'=>null,
   'is_third_place'=>false,'is_bye'=>false,'confirmed'=>false,'start'=>null,'end'=>null,'phase'=>'Grupos','phase_rank'=>0,
   'feeders'=>['a'=>null,'b'=>null],'seed_labels'=>['a'=>null,'b'=>null],'players'=>['a'=>[],'b'=>[]],
   'info'=>['label'=>"Cat$cat · M$id",'short'=>"M$id",'vs'=>'','court'=>null,'time'=>null]];
  $r = array_replace($d,$o);
  if(isset($o['at'])){ $r['start']=t($o['at']); $r['end']=$r['start']+75*60; unset($r['at']); }
  return $r;
}
$pass=0;$fail=0;
function check($name,$cond){ global $pass,$fail; if($cond){$pass++; echo "  ok   $name\n";} else {$fail++; echo "  FAIL $name\n";} }
function kinds($order,$cat,$key){ foreach($order as $c) if($c['category_id']==$cat) return array_column($c[$key],'kind'); return []; }

// ---------- S1: Mexicano, both R2 on same day ----------
echo "S1 Mexicano R2 same day + 3rd match in another category\n";
$g=100;
$rows=[
 11=>row(11,1,['group_id'=>$g,'round'=>1,'at'=>'2026-10-01 10:00','players'=>['a'=>pl('Juan','Pedro'),'b'=>pl('Ana','Luis')]]),
 12=>row(12,1,['group_id'=>$g,'round'=>1,'at'=>'2026-10-01 10:00','players'=>['a'=>pl('Eva','Rosa'),'b'=>pl('Tom','Sam')]]),
 13=>row(13,1,['group_id'=>$g,'round'=>2,'at'=>'2026-10-01 13:00','feeders'=>['a'=>['id'=>11,'source'=>'winner'],'b'=>['id'=>12,'source'=>'loser']]]),
 14=>row(14,1,['group_id'=>$g,'round'=>2,'at'=>'2026-10-01 13:00','feeders'=>['a'=>['id'=>12,'source'=>'winner'],'b'=>['id'=>11,'source'=>'loser']]]),
 21=>row(21,2,['group_id'=>200,'at'=>'2026-10-01 17:00','players'=>['a'=>pl('Juan','Beto'),'b'=>pl('Ximena','Yolo')]]),
];
$r=(new ScheduleAudit(30*60))->run($rows,[1=>['A'=>$g],2=>['A'=>200]]);
$juan=array_values(array_filter($r['load'],fn($x)=>$x['player']==='Juan'));
check('Juan flagged on Thu with 3', count($juan)===1 && $juan[0]['total']===3 && $juan[0]['sure']===3 && $juan[0]['possible']===0);
check('R2 counted once as r2_sure with 1 alt', count(array_filter($juan[0]['matches'],fn($m)=>$m['kind']==='r2_sure' && count($m['alts'])===1))===1);
check('Pedro (2 matches) not flagged', !array_filter($r['load'],fn($x)=>$x['player']==='Pedro'));
check('R2 alternatives at same time are NOT a conflict', empty($r['conflicts']));
check('No order errors', kinds($r['order'],1,'errors')===[]);

// ---------- S2: R2 split across days + possible overlap ----------
echo "S2 Mexicano R2 on different days\n";
$rows[13]=row(13,1,['group_id'=>$g,'round'=>2,'at'=>'2026-10-01 17:00','feeders'=>['a'=>['id'=>11,'source'=>'winner'],'b'=>['id'=>12,'source'=>'loser']]]);
$rows[14]=row(14,1,['group_id'=>$g,'round'=>2,'at'=>'2026-10-02 10:00','feeders'=>['a'=>['id'=>12,'source'=>'winner'],'b'=>['id'=>11,'source'=>'loser']]]);
$r=(new ScheduleAudit(30*60))->run($rows,[1=>['A'=>$g],2=>['A'=>200]]);
$juan=array_values(array_filter($r['load'],fn($x)=>$x['player']==='Juan'));
check('Juan Thu = 2 sure + 1 possible', count($juan)===1 && $juan[0]['sure']===2 && $juan[0]['possible']===1 && $juan[0]['day']==='2026-10-01');
check('Day label is Spanish', $juan[0]['day_label']==='Jue 01 Oct');
$pc=array_values(array_filter($r['conflicts'],fn($c)=>$c['player']==='Juan'));
check('Juan: possible overlap R2(17:00) vs 4ta(17:00)', count($pc)===1 && $pc[0]['severity']==='overlap' && $pc[0]['possible']===true);
check('Ana (R1a loser side too) also possible overlap? no — Ana not in cat2', !array_filter($r['conflicts'],fn($c)=>$c['player']==='Ana'));

// ---------- S3: order errors on Mexicano ----------
echo "S3 R2 before R1 / R1 unscheduled / short rest\n";
$rows2=$rows;
$rows2[13]=row(13,1,['group_id'=>$g,'round'=>2,'at'=>'2026-10-01 09:00','feeders'=>['a'=>['id'=>11,'source'=>'winner'],'b'=>['id'=>12,'source'=>'loser']]]);
$rows2[12]=row(12,1,['group_id'=>$g,'round'=>1,'players'=>['a'=>pl('Eva','Rosa'),'b'=>pl('Tom','Sam')]]); // unscheduled
$rows2[14]=row(14,1,['group_id'=>$g,'round'=>2,'at'=>'2026-10-01 11:20','feeders'=>['a'=>['id'=>12,'source'=>'winner'],'b'=>['id'=>11,'source'=>'loser']]]);
$r=(new ScheduleAudit(30*60))->run($rows2,[1=>['A'=>$g],2=>['A'=>200]]);
$e=kinds($r['order'],1,'errors'); $w=kinds($r['order'],1,'warnings');
check('before_dep for 13', in_array('before_dep',$e));
check('unscheduled_dep for 13 and 14 (R1b unscheduled)', count(array_keys($e,'unscheduled_dep'))===2);
check('rest_dep 11:15→11:20 for 14', in_array('rest_dep',$w));
check('Cat with errors sorted first', $r['order'][0]['category_id']===1);

// ---------- S4: bracket from groups ----------
echo "S4 Hybrid: groups A,B → SF → F (+3rd)\n";
$A=301;$B=302;
$b=[
 1=>row(1,5,['group_id'=>$A,'at'=>'2026-10-03 09:00','players'=>['a'=>pl('a1'),'b'=>pl('a2')]]),
 2=>row(2,5,['group_id'=>$A,'at'=>'2026-10-03 10:30','players'=>['a'=>pl('a1'),'b'=>pl('a3')]]),
 3=>row(3,5,['group_id'=>$B,'at'=>'2026-10-03 09:00','players'=>['a'=>pl('b1'),'b'=>pl('b2')]]),
 4=>row(4,5,['group_id'=>$B,'at'=>'2026-10-03 15:00','players'=>['a'=>pl('b1'),'b'=>pl('b3')]]),
 5=>row(5,5,['round'=>1,'phase'=>'SF','phase_rank'=>1,'at'=>'2026-10-03 14:00','seed_labels'=>['a'=>'A1','b'=>'B2']]),
 6=>row(6,5,['round'=>1,'phase'=>'SF','phase_rank'=>1,'at'=>'2026-10-03 17:00','seed_labels'=>['a'=>'B1','b'=>'A2']]),
 7=>row(7,5,['round'=>2,'phase'=>'F','phase_rank'=>2,'at'=>'2026-10-03 18:00','feeders'=>['a'=>['id'=>5,'source'=>'winner'],'b'=>['id'=>6,'source'=>'winner']]]),
 8=>row(8,5,['round'=>2,'is_third_place'=>true,'phase'=>'3er lugar','phase_rank'=>2,'at'=>'2026-10-03 18:00','feeders'=>['a'=>['id'=>5,'source'=>'loser'],'b'=>['id'=>6,'source'=>'loser']]]),
];
$r=(new ScheduleAudit(30*60))->run($b,[5=>['A'=>$A,'B'=>$B]]);
$e=kinds($r['order'],5,'errors'); $w=kinds($r['order'],5,'warnings');
check('SF1 (A1 vs B2) at 14:00 before group B ends 16:15 → before_group', in_array('before_group',$e));
check('F and 3rd at 18:00 before SF2 ends 18:15 → 2 before_dep', count(array_keys($e,'before_dep'))===2);
check('round_order warnings (groups→SF, SF→F)', count(array_keys($w,'round_order'))===2);
$msg=array_column(array_values(array_filter($r['order'],fn($c)=>$c['category_id']==5))[0]['errors'],'message');
echo "    e.g. ".$msg[0]."\n";
check('Bracket players not counted in load (unknown pairs)', empty($r['load']));

// ---------- S5: BYE transparency + Q label + confirmed feeder ----------
echo "S5 BYE feeder, Q label, confirmed feeder\n";
$c=[
 1=>row(1,6,['group_id'=>$A,'at'=>'2026-10-03 09:00','players'=>['a'=>pl('a1'),'b'=>pl('a2')]]),
 3=>row(3,6,['group_id'=>$B,'at'=>'2026-10-03 16:00','players'=>['a'=>pl('b1'),'b'=>pl('b2')]]),
 5=>row(5,6,['round'=>1,'phase'=>'SF','phase_rank'=>1,'is_bye'=>true,'seed_labels'=>['a'=>'A1','b'=>'BYE']]),
 6=>row(6,6,['round'=>1,'phase'=>'SF','phase_rank'=>1,'at'=>'2026-10-03 18:00','seed_labels'=>['a'=>'B1','b'=>'Q1']]),
 7=>row(7,6,['round'=>2,'phase'=>'F','phase_rank'=>2,'at'=>'2026-10-03 12:00','feeders'=>['a'=>['id'=>5,'source'=>'winner'],'b'=>['id'=>6,'source'=>'winner']]]),
];
$r=(new ScheduleAudit(30*60))->run($c,[6=>['A'=>$A,'B'=>$B]]);
$e=kinds($r['order'],6,'errors');
check('F before SF2 → before_dep', in_array('before_dep',$e));
check('F waits on group A through BYE (A ends 10:15 < 12:00 → fine) — no group error for A', !array_filter(array_values(array_filter($r['order'],fn($x)=>$x['category_id']==6))[0]['errors'], fn($x)=>str_contains($x['message'],'Grupo A')));
check('BYE match itself never flagged as unscheduled_dep', !in_array('unscheduled_dep',$e));
$c[3]['confirmed']=true; $c[3]['start']=null; $c[3]['end']=null; // B already played (no time)
$c[7]=row(7,6,['round'=>2,'phase'=>'F','phase_rank'=>2,'at'=>'2026-10-03 20:00','feeders'=>['a'=>['id'=>5,'source'=>'winner'],'b'=>['id'=>6,'source'=>'winner']]]);
$r=(new ScheduleAudit(30*60))->run($c,[6=>['A'=>$A,'B'=>$B]]);
check('Clean category once fixed (confirmed feeder satisfied)', kinds($r['order'],6,'errors')===[]);

// ---------- S6: sure-only conflicts unchanged ----------
echo "S6 legacy sure conflicts\n";
$d=[
 1=>row(1,7,['group_id'=>1,'at'=>'2026-10-01 10:00','players'=>['a'=>pl('Kim','Lee'),'b'=>pl('X','Y')]]),
 2=>row(2,8,['group_id'=>2,'at'=>'2026-10-01 10:30','players'=>['a'=>pl('Kim','Moe'),'b'=>pl('Z','W')]]),
 3=>row(3,8,['group_id'=>2,'at'=>'2026-10-01 12:00','players'=>['a'=>pl('Kim','Moe'),'b'=>pl('Q','R')]]),
];
$r=(new ScheduleAudit(30*60))->run($d,[7=>['A'=>1],8=>['A'=>2]]);
$k=array_values(array_filter($r['conflicts'],fn($c)=>$c['player']==='Kim'));
check('Kim overlap (sure) first, then rest', count($k)===2 && $k[0]['severity']==='overlap' && !$k[0]['possible'] && $k[1]['severity']==='rest');
check('Kim 3 matches in a day flagged', count(array_filter($r['load'],fn($x)=>$x['player']==='Kim' && $x['total']===3))===1);
$r0=(new ScheduleAudit(0))->run($d,[7=>['A'=>1],8=>['A'=>2]]);
check('rest=0 disables rest conflicts', count(array_filter($r0['conflicts'],fn($c)=>$c['severity']==='rest'))===0);

echo "\n$pass passed, $fail failed\n";
echo "\nSample messages (S4):\n";
$r=(new ScheduleAudit(30*60))->run($b,[5=>['A'=>$A,'B'=>$B]]);
foreach($r['order'][0]['errors'] as $x) echo "  ERR  ".$x['message']."\n";
foreach($r['order'][0]['warnings'] as $x) echo "  WARN ".$x['message']."\n";
