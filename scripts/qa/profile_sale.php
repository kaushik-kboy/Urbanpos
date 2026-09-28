<?php
// Statement-level profile of ONE sale POST (QA db). php scripts/qa/profile_sale.php
foreach (['DB_CONNECTION'=>'mysql','DB_HOST'=>'127.0.0.1','DB_PORT'=>'3307','DB_DATABASE'=>'urban_pos_qa','APP_ENV'=>'testing','CACHE_STORE'=>'database','SESSION_DRIVER'=>'array','LOG_CHANNEL'=>'null'] as $k=>$v){putenv("$k=$v");$_ENV[$k]=$_SERVER[$k]=$v;}
require __DIR__.'/../../vendor/autoload.php'; $app=require __DIR__.'/../../bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB; use Illuminate\Http\Request;
$user = App\Models\User::where('email','qa-load-1@example.com')->first(); $app['auth']->guard('web')->setUser($user);
$hot = DB::table('items')->where('item_code','like','LOADHOT%')->limit(2)->get(); $cust=(int)DB::table('customers')->where('name','like','QA Cust%')->inRandomOrder()->value('id'); $tender=(int)DB::table('tender_types')->where('name','Cash')->value('id');
$payload=['posting_key'=>(string)Illuminate\Support\Str::uuid(),'bill_date'=>now()->format('Y-m-d H:i:s'),'customer_id'=>$cust,'branch_id'=>$user->branch_id,'invoice_type'=>'Retail Invoice','delivery_type'=>'Delivered','sales_type'=>'Local',
 'items'=>$hot->map(fn($i)=>['item_id'=>$i->id,'qty'=>mt_rand(2,9),'sell_price'=>100,'mrp'=>120,'disc_percent'=>0,'disc_amount'=>0,'gst_percent'=>18])->all(),'payments'=>[['tender_type_id'=>$tender,'amount'=>0]]];
$payload['payments'][0]['amount']=array_sum(array_map(fn($l)=>$l['qty']*100,$payload['items']));
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
$run=function($payload) use($kernel,$app){ $log=[]; $t0=microtime(true); DB::listen(function($q) use(&$log,$t0){$log[]=[round((microtime(true)-$t0)*1000),$q->time,substr(preg_replace('/\s+/',' ',$q->sql),0,110)];});
 $r=$kernel->handle(Request::create('/sales/sales-bills','POST',$payload,[],[],['HTTP_ACCEPT'=>'application/json'])); if ($r->getStatusCode() >= 400) { echo substr($r->getContent(),0,300)."
"; } return [$r->getStatusCode(),round((microtime(true)-$t0)*1000),$log]; };
$warm=$payload; $warm['items'][0]['qty']=1; $warm['payments'][0]['amount']=array_sum(array_map(fn($l)=>$l['qty']*100,$warm['items'])); $warm['posting_key']=(string)Illuminate\Support\Str::uuid(); $run($warm); // warm (different content: avoids the duplicate-content guard)
sleep(11); [$code,$total,$log]=$run($payload);
echo "status $code, total {$total} ms, ".count($log)." statements\n";
foreach($log as [$at,$ms,$sql]) if($ms>=3||preg_match('/^(begin|commit|select .*for update|insert into `document_sequences|update `document_sequences)/i',$sql)) printf("  t+%4d ms  %5.1f ms  %s\n",$at,$ms,$sql);
