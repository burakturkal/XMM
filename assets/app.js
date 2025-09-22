jQuery(function($){
  $(document).on('keydown', '#al-add-form', function(e){
    if(e.key==='Enter' && !$(e.target).is('textarea')){
      e.preventDefault();
      $(this).find('button[type=submit],button[name=al_add_job]').first().click();
    }
  });

  function checkDedupe(){
    const cid = $('#al_customer_id').val();
    if(!cid) return;
    const data = {
      action:'al_dedupe_check',
      nonce:ALCFG.nonce_dedupe,
      customer_id: cid,
      job_title: $('#al_job_title').val(),
      company: $('#al_company').val(),
      website: $('#al_website').val()
    };
    $.post(ALCFG.ajax, data).done(res=>{
      if(res.success && res.data.count>0){
        alToast('Similar application exists in last 60 days ('+res.data.count+').', true);
      }
    });
  }
  $(document).on('blur', '#al_job_title,#al_company,#al_website', checkDedupe);

  $(document).on('click','td[data-edit]',function(){
    const td=$(this); if(td.find('input,select').length) return;
    const field=td.data('edit'); const id=td.closest('tr').data('id');
    const oldVal=(td.text()||'').trim();
    let input;
    if(field==='applied_date'){ input=$('<input type="date">').val(oldVal); }
    else if(field==='status'){
      input=$('<select>')
        .append('<option>Applied</option><option>Interview</option><option>Offer</option><option>Rejected</option><option>Ghosted</option>')
        .val(oldVal||'Applied');
    } else { input=$('<input type="text">').val(oldVal); }
    td.empty().append(input); input.focus().select();
    function save(val){
      $.post(ALCFG.ajax,{action:'al_inline_update',nonce:ALCFG.nonce_inline,id:id,field:field,value:val})
      .done(res=>{ if(res.success){ td.text(res.data.value); alToast('Saved'); } else { td.text(oldVal); alToast('Error',true); } })
      .fail(()=>{ td.text(oldVal); alToast('Network error',true); });
    }
    input.on('keydown',e=>{ if(e.key==='Enter'){ e.preventDefault(); save(input.val()); } if(e.key==='Escape'){ td.text(oldVal);} })
         .on('blur', ()=> save(input.val()));
  });
});

window.alToast = function(msg, isError){
  const t = jQuery('<div class="al-toast">').toggleClass('error', !!isError).text(msg);
  jQuery('body').append(t); setTimeout(()=> t.fadeOut(200,()=>t.remove()), 2200);
};