(function(){
  'use strict';
  function request(data){
    data.append('action','snp_interact');
    data.append('nonce',snpNews.nonce);
    return fetch(snpNews.ajaxUrl,{method:'POST',credentials:'same-origin',cache:'no-store',body:data}).then(function(response){return response.json().then(function(json){if(!response.ok||!json.success){throw new Error(json.data&&json.data.message?json.data.message:'The action could not be completed.');}return json.data;});});
  }
  function postId(element){var owner=element.closest('[data-post-id]');return owner?owner.getAttribute('data-post-id'):'';}
  document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('.snp-publish-form select[name="topic"]').forEach(function(select){
      var box=select.form.querySelector('[data-snp-case-consent]');
      function update(){var active=select.value==='patient-cases';box.hidden=!active;box.querySelectorAll('input,textarea').forEach(function(input){if(input.name!=='case_image_consent'){input.required=active;}if(!active&&input.type==='checkbox'){input.checked=false;}});}
      select.addEventListener('change',update);update();
    });
    document.querySelectorAll('.snp-publish-form input[type="file"]').forEach(function(input){input.addEventListener('change',function(){if(this.files[0]&&this.files[0].size>5*1024*1024){window.alert('Please choose an image that is 5 MB or smaller.');this.value='';}});});
  });
  document.addEventListener('click',function(event){
    var button=event.target.closest('[data-snp-action]');
    if(button){
      event.preventDefault();var data=new FormData();data.append('kind',button.getAttribute('data-snp-action'));data.append('postId',postId(button));button.disabled=true;
      request(data).then(function(result){button.classList.toggle('is-active',!!result.active);button.setAttribute('aria-pressed',result.active?'true':'false');button.setAttribute('data-snp-action',result.nextKind);if(result.count!==undefined){button.innerHTML=result.label+' <span data-snp-count>'+result.count+'</span>';}else{button.textContent=result.label;}}).catch(function(error){window.alert(error.message);if(/account|log in/i.test(error.message)){window.location.href=snpNews.loginUrl;}}).finally(function(){button.disabled=false;});
      return;
    }
    var share=event.target.closest('[data-snp-share]');
    if(share){event.preventDefault();var payload={title:share.dataset.title,url:share.dataset.url};if(navigator.share){navigator.share(payload).catch(function(){});}else if(navigator.clipboard){navigator.clipboard.writeText(payload.url).then(function(){window.alert('Publication link copied.');});}}
  });
  document.addEventListener('submit',function(event){
    var form=event.target.closest('[data-snp-report]');if(!form){return;}event.preventDefault();var data=new FormData(form);data.append('kind','report');data.append('postId',postId(form));var submit=form.querySelector('button[type="submit"]');submit.disabled=true;
    request(data).then(function(result){form.innerHTML='<p class="snp-action-message" role="status">'+result.message+'</p>';}).catch(function(error){window.alert(error.message);}).finally(function(){submit.disabled=false;});
  });
})();
