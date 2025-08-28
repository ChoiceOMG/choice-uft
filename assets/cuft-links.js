/* Choice Universal Form Tracker — link click tracking (non-blocking, defensive)
   - Sends phone_click for tel: links (with normalized number)
   - Also sends email_click for mailto: links (optional bonus)
   - Never prevents default; wrapped in try/catch so it cannot interfere
*/
(function () {
    var DEBUG = !!(window.cuftDebug);
    function log(){ try{ if(DEBUG && window.console) console.log.apply(console, ['[CUFT Links]'].concat(Array.prototype.slice.call(arguments))); }catch(e){} }
  
    function getDL(){
      try { return (window.dataLayer = window.dataLayer || []); }
      catch (e) { return { push: function(){} }; }
    }
  
    function normPhone(href){
      try { return href.replace(/^tel:/i,'').trim().replace(/(?!^\+)[^\d]/g,''); }
      catch(e){ return href || ''; }
    }
  
    function onClick(e){
      try {
        var a = e.target && e.target.closest ? e.target.closest('a[href^="tel:"], a[href^="mailto:"]') : null;
        if (!a) return;
  
        var href = a.getAttribute('href') || '';
        if (/^tel:/i.test(href)) {
          var phone = normPhone(href);
          getDL().push({
            event: 'phone_click',
            phone: phone || null,
            href: href,
            clickedAt: new Date().toISOString()
          });
          log('phone_click:', phone);
          // do NOT prevent default; we're non-interfering by design
          return;
        }
  
        // Optional: email click (kept here since user snippet included mailto:)
        if (/^mailto:/i.test(href)) {
          var email = (href.replace(/^mailto:/i,'').split('?')[0] || '').trim();
          getDL().push({
            event: 'email_click',
            email: email || null,
            href: href,
            clickedAt: new Date().toISOString()
          });
          log('email_click:', email);
          return;
        }
      } catch(err){
        log('link tracking error:', err);
        // swallow error — never interfere
      }
    }
  
    // Use capture phase so we push even if other handlers stop propagation
    try { document.addEventListener('click', onClick, true); } catch(e){}
  })();
  