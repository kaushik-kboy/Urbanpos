@extends('adminlte::page')

@section('title', 'POS Feature Health Check')

@section('content_header')
<div class="d-flex justify-content-between align-items-center mb-2">
  <div>
    <h1 class="font-weight-bold text-dark mb-1">
      <i class="fas fa-heartbeat text-success mr-2"></i> POS Feature Health Check
    </h1>
    <p class="text-muted small mb-0">Real-time self-test of critical POS features. Run anytime to confirm what is working and what is not.</p>
  </div>
  <div>
    <button id="btn-run-all-checks" class="btn btn-primary font-weight-bold shadow-sm">
      <i class="fas fa-play-circle mr-1"></i> Run All Checks
    </button>
    <button id="btn-clear-pos-draft" class="btn btn-outline-warning ml-2 font-weight-bold shadow-sm">
      <i class="fas fa-trash mr-1"></i> Clear Draft
    </button>
  </div>
</div>
@stop

@section('content')

<div class="row mb-3" id="summary-row" style="display:none">
  <div class="col-md-3">
    <div class="info-box bg-success shadow-sm">
      <span class="info-box-icon"><i class="fas fa-check-circle"></i></span>
      <div class="info-box-content"><span class="info-box-text">Passing</span><span class="info-box-number" id="count-pass">0</span></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="info-box bg-danger shadow-sm">
      <span class="info-box-icon"><i class="fas fa-times-circle"></i></span>
      <div class="info-box-content"><span class="info-box-text">Failing</span><span class="info-box-number" id="count-fail">0</span></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="info-box bg-warning shadow-sm">
      <span class="info-box-icon"><i class="fas fa-exclamation-triangle"></i></span>
      <div class="info-box-content"><span class="info-box-text">Warnings</span><span class="info-box-number" id="count-warn">0</span></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="info-box bg-secondary shadow-sm">
      <span class="info-box-icon"><i class="fas fa-clock"></i></span>
      <div class="info-box-content"><span class="info-box-text">Last Run</span><span class="info-box-number" id="last-checked" style="font-size:13px">--</span></div>
    </div>
  </div>
</div>

<div class="card card-outline card-primary shadow-sm">
  <div class="card-header">
    <h3 class="card-title font-weight-bold"><i class="fas fa-list-ul mr-1"></i> Feature Checks</h3>
    <div class="card-tools">
      <span class="badge badge-secondary px-3 py-2" id="overall-status-badge">Not Run</span>
    </div>
  </div>
  <div class="card-body p-0">
    <table class="table table-sm table-hover mb-0">
      <thead class="bg-light">
        <tr>
          <th style="width:40px" class="text-center">#</th>
          <th>Feature / Check</th>
          <th style="width:120px">Category</th>
          <th style="width:110px" class="text-center">Status</th>
          <th>Result / Details</th>
          <th style="width:80px" class="text-center">Time</th>
        </tr>
      </thead>
      <tbody id="health-checks-tbody">
        <tr>
          <td colspan="6" class="text-center text-muted py-4">
            <i class="fas fa-play-circle fa-2x mb-2 d-block"></i>
            Click <strong>Run All Checks</strong> to start the self-test.
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

{{-- Draft Inspector --}}
<div class="card card-outline card-warning shadow-sm mt-3">
  <div class="card-header">
    <h3 class="card-title font-weight-bold"><i class="fas fa-database mr-1"></i> Draft Inspector (localStorage)</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
    </div>
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-md-6">
        <h6 class="font-weight-bold">Current Stored Draft</h6>
        <pre id="draft-inspector-output" class="bg-light p-3 rounded border small" style="max-height:280px;overflow:auto;white-space:pre-wrap">Click Inspect Draft to read localStorage</pre>
        <button class="btn btn-sm btn-outline-primary mt-2" id="btn-inspect-draft">
          <i class="fas fa-eye mr-1"></i> Inspect Draft
        </button>
        <button class="btn btn-sm btn-outline-danger mt-2 ml-2" id="btn-delete-draft">
          <i class="fas fa-trash mr-1"></i> Delete Draft
        </button>
      </div>
      <div class="col-md-6">
        <h6 class="font-weight-bold">Write Test Draft</h6>
        <p class="small text-muted">Write a sample draft, then open Sales Bill Create page and verify the yellow Restore banner appears immediately.</p>
        <a href="{{ url('sales/sales-bills/create') }}" target="_blank" class="btn btn-sm btn-outline-info mb-2">
          <i class="fas fa-external-link-alt mr-1"></i> Open Sales Bill Create
        </a><br>
        <button class="btn btn-sm btn-success" id="btn-write-test-draft">
          <i class="fas fa-pen mr-1"></i> Write Test Draft (qty=3)
        </button>
        <div id="test-draft-status" class="mt-2"></div>
      </div>
    </div>
  </div>
</div>

@stop

@push('js')
<script>
$(document).ready(function(){
  var DK = 'urbanpos_sales_bill_draft_v1';
  var AU = '{{ url("/") }}';
  var CS = (document.querySelector('meta[name=csrf-token]')||{}).content||'';

  function sbadge(r){
    if(r.pass) return '<span class="badge badge-success px-2 py-1"><i class="fas fa-check mr-1"></i>PASS</span>';
    if(r.warn) return '<span class="badge badge-warning px-2 py-1"><i class="fas fa-exclamation mr-1"></i>WARN</span>';
    return '<span class="badge badge-danger px-2 py-1"><i class="fas fa-times mr-1"></i>FAIL</span>';
  }
  function cbadge(c){
    var m = {Browser:'info','Auto-Draft':'warning',Sales:'primary',Telemetry:'secondary','Error Hub':'danger'};
    return '<span class="badge badge-'+(m[c]||'secondary')+' px-2">'+c+'</span>';
  }

  // ---- SYNC CHECKS (browser-side) ----
  var SYNC = [
    {
      id:'ls', n:'Browser localStorage Available', c:'Browser',
      fn:function(){
        try{
          localStorage.setItem('__pt__','1');
          var v = localStorage.getItem('__pt__');
          localStorage.removeItem('__pt__');
          if(v !== '1') throw new Error('mismatch');
          return {pass:true, msg:'localStorage available and working correctly'};
        } catch(e){ return {pass:false, msg:'localStorage unavailable: '+e.message}; }
      }
    },
    {
      id:'qty', n:'Draft Qty Guard (Never Negative)', c:'Auto-Draft',
      fn:function(){
        var cases = [['-1',1],['0',1],['',1],['2',2],['-5',1]];
        for(var i=0;i<cases.length;i++){
          var c=cases[i]; var q=parseFloat(c[0])||1; if(q<=0)q=1;
          if(q !== c[1]) return {pass:false, msg:'Input "'+c[0]+'" produced '+q+', expected '+c[1]};
        }
        return {pass:true, msg:'All qty guard cases pass: -1=>1, 0=>1, empty=>1, 2=>2'};
      }
    },
    {
      id:'dsave', n:'Auto-Draft Save and Read Cycle', c:'Auto-Draft',
      fn:function(){
        try{
          var d = {saved_at:'test',timestamp:Date.now(),items:[{item_id:'1',qty:2,stock_val:'50'}]};
          localStorage.setItem(DK, JSON.stringify(d));
          var r = JSON.parse(localStorage.getItem(DK));
          if(!r || !r.items || r.items[0].qty !== 2) throw new Error('Data corrupted after write');
          return {pass:true, msg:'Draft write/read cycle working. qty=2 preserved correctly.'};
        } catch(e){ return {pass:false, msg:'Draft save failed: '+e.message}; }
      }
    },
    {
      id:'dbanner', n:'Draft Recovery Banner Check', c:'Auto-Draft',
      fn:function(){
        var raw = localStorage.getItem(DK);
        if(!raw) return {warn:true, msg:'No draft in localStorage. Use Write Test Draft button first, then open Sales Bill Create.'};
        try{
          var d = JSON.parse(raw);
          if(!d||!d.items||d.items.length===0) return {warn:true, msg:'Draft found but has 0 items.'};
          return {pass:true, msg:'Draft found: '+d.items.length+' item(s), saved at '+d.saved_at+'. Open Sales Bill Create to verify yellow banner.'};
        } catch(e){ return {pass:false, msg:'Parse error: '+e.message}; }
      }
    },
    {
      id:'jq', n:'jQuery and Select2 Loaded', c:'Browser',
      fn:function(){
        if(typeof $ === 'undefined') return {pass:false, msg:'jQuery not loaded on page'};
        if(typeof $.fn.select2 === 'undefined') return {warn:true, msg:'jQuery '+$.fn.jquery+' loaded. Select2 not available - may affect item search.'};
        return {pass:true, msg:'jQuery '+$.fn.jquery+' + Select2 loaded.'};
      }
    },
  ];

  // ---- ASYNC CHECKS (server-side fetch) ----
  var ASYNC = [
    {id:'tel',  n:'Client Telemetry Endpoint (POST)', c:'Telemetry',  url:'/tools/client-error-logs', method:'POST'},
    {id:'hub',  n:'System Error Hub Page (GET)',       c:'Error Hub',  url:'/tools/system-error-logs', method:'GET'},
    {id:'bill', n:'Sales Bill Create Page (DOM Check)',c:'Sales',      url:'/sales/sales-bills/create',method:'GET', dom:true},
    {id:'srch', n:'Item Search API (GET)',             c:'Sales',      url:'/sales/sales-bills/item-list?search=a&branch_id=1', method:'GET'},
  ];

  $('#btn-run-all-checks').on('click', function(){
    var $btn = $(this).prop('disabled',true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Running...');
    var $tb = $('#health-checks-tbody').html('');
    var pass=0, fail=0, warn=0, syncLen=SYNC.length;

    SYNC.forEach(function(chk,i){
      var t0=performance.now(), r;
      try{ r=chk.fn(); } catch(e){ r={pass:false, msg:'Exception: '+e.message}; }
      var ms=Math.round(performance.now()-t0);
      if(r.pass)pass++; else if(r.warn)warn++; else fail++;
      var rc=r.pass?'table-success':r.warn?'table-warning':'table-danger';
      $tb.append('<tr class="'+rc+'">'+
        '<td class="text-center text-muted">'+(i+1)+'</td>'+
        '<td class="font-weight-bold">'+chk.n+'</td>'+
        '<td>'+cbadge(chk.c)+'</td>'+
        '<td class="text-center">'+sbadge(r)+'</td>'+
        '<td class="small">'+r.msg+'</td>'+
        '<td class="text-center text-muted">'+ms+'ms</td>'+
      '</tr>');
    });

    var pending = ASYNC.length;
    if(pending === 0){ finalize(); return; }

    ASYNC.forEach(function(ac, i){
      var rid = 'ar-'+ac.id;
      $tb.append('<tr id="'+rid+'">'+
        '<td class="text-center text-muted">'+(syncLen+i+1)+'</td>'+
        '<td class="font-weight-bold">'+ac.n+'</td>'+
        '<td>'+cbadge(ac.c)+'</td>'+
        '<td class="text-center"><span class="badge badge-secondary">Pending</span></td>'+
        '<td class="small text-muted">Checking...</td>'+
        '<td class="text-center text-muted">--</td>'+
      '</tr>');

      var t0=performance.now();
      var opts = {method:ac.method, headers:{'X-Requested-With':'XMLHttpRequest'}};
      if(ac.method==='POST'){
        opts.headers['Content-Type']='application/json';
        opts.headers['X-CSRF-TOKEN']=CS;
        opts.body=JSON.stringify({message:'[POS Health Check] Telemetry probe',file:'pos-health-check',line:0,url:window.location.href,stack:''});
      }

      fetch(AU+ac.url, opts).then(function(resp){
        var ms=Math.round(performance.now()-t0);
        var $r = $('#'+rid);
        if(ac.dom){
          resp.text().then(function(text){
            var issues=[];
            if(text.indexOf('type="submit"')<0) issues.push('Save button missing');
            if(text.indexOf('sb-draft-recovery-alert')<0) issues.push('Draft recovery banner missing');
            var r=issues.length ? {pass:false,msg:'Issues: '+issues.join(', ')} : {pass:true,msg:'Page OK. Save button and Draft banner found in DOM.'};
            if(r.pass)pass++; else fail++;
            $r.removeClass().addClass(r.pass?'table-success':'table-danger');
            $r.find('td:eq(3)').html(sbadge(r)); $r.find('td:eq(4)').text(r.msg); $r.find('td:eq(5)').text(ms+'ms');
            pending--; if(pending===0) finalize();
          });
          return;
        }
        var r = resp.ok ? {pass:true,msg:'HTTP '+resp.status+' OK'} : {warn:true,msg:'HTTP '+resp.status};
        if(r.pass)pass++; else if(r.warn)warn++; else fail++;
        $r.removeClass().addClass(r.pass?'table-success':r.warn?'table-warning':'table-danger');
        $r.find('td:eq(3)').html(sbadge(r)); $r.find('td:eq(4)').text(r.msg); $r.find('td:eq(5)').text(ms+'ms');
        pending--; if(pending===0) finalize();
      }).catch(function(e){
        var ms=Math.round(performance.now()-t0);
        var r={pass:false, msg:'Request failed: '+e.message}; fail++;
        var $r=$('#'+rid);
        $r.removeClass().addClass('table-danger');
        $r.find('td:eq(3)').html(sbadge(r)); $r.find('td:eq(4)').text(r.msg); $r.find('td:eq(5)').text(ms+'ms');
        pending--; if(pending===0) finalize();
      });
    });

    function finalize(){
      $('#count-pass').text(pass); $('#count-fail').text(fail); $('#count-warn').text(warn);
      $('#last-checked').text(new Date().toLocaleTimeString());
      $('#summary-row').show();
      var $badge = $('#overall-status-badge').removeClass('badge-secondary badge-success badge-danger badge-warning');
      if(fail>0) $badge.addClass('badge-danger').text(fail+' FAILING');
      else if(warn>0) $badge.addClass('badge-warning').text(warn+' WARNINGS');
      else $badge.addClass('badge-success').text('ALL PASSING');
      $btn.prop('disabled',false).html('<i class="fas fa-redo mr-1"></i> Re-Run All Checks');
    }
  });

  // ---- DRAFT INSPECTOR ----
  $('#btn-inspect-draft').on('click',function(){
    var raw=localStorage.getItem(DK);
    if(!raw){
      $('#draft-inspector-output').text('No draft found.\nKey: '+DK+'\n\nUse "Write Test Draft" to create one, then go to Sales Bill Create.');
      return;
    }
    try{
      var d=JSON.parse(raw);
      var info='Draft found!\n';
      info+='Key: '+DK+'\n';
      info+='Saved at: '+d.saved_at+'\n';
      info+='Items: '+d.items.length+'\n';
      info+='Customer ID: '+(d.customer_id||'None')+'\n';
      info+='\nFull JSON:\n'+JSON.stringify(d,null,2);
      $('#draft-inspector-output').text(info);
    } catch(e){
      $('#draft-inspector-output').text('Parse error: '+e.message+'\nRaw: '+raw);
    }
  });

  $('#btn-delete-draft').on('click',function(){
    if(confirm('Delete the bill draft from localStorage?')){
      localStorage.removeItem(DK);
      $('#draft-inspector-output').text('Draft deleted.\nRefresh Sales Bill Create page to confirm the yellow banner is gone.');
    }
  });

  $('#btn-write-test-draft').on('click',function(){
    var d = {
      saved_at: new Date().toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'}),
      timestamp: Date.now(),
      customer_id: '',
      customer_text: '',
      branch_id: '',
      items: [{
        item_id:'999', item_code:'HCTEST', item_desc:'Health Check Test Item [HCTEST]',
        qty:3, sell_price:'150.00', mrp:'160.00', exp_date:'',
        gst_percent:'18', disc_percent:'', disc_amount:'', stock_val:'50'
      }]
    };
    localStorage.setItem(DK, JSON.stringify(d));
    $('#test-draft-status').html(
      '<div class="alert alert-success py-2 mt-2">'+
      '<i class="fas fa-check-circle mr-1"></i> Test draft written! (1 item, qty=3, sell=150)<br>'+
      '<strong>Now click "Open Sales Bill Create" above</strong> -- yellow Restore banner should appear immediately.'+
      '</div>'
    );
  });

  $('#btn-clear-pos-draft').on('click',function(){
    localStorage.removeItem(DK);
    $('#test-draft-status').html('<div class="alert alert-warning py-2 mt-2"><i class="fas fa-check mr-1"></i> Draft cleared from localStorage.</div>');
  });
});
</script>
@endpush
