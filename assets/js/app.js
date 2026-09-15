// Boyambi App JS
document.addEventListener('DOMContentLoaded',()=>{
  // Sidebar toggle mobile
  const sidebar=document.querySelector('.app-sidebar');
  const toggle=document.querySelectorAll('[data-toggle-sidebar]');
  toggle.forEach(b=>b.addEventListener('click',()=> sidebar.classList.toggle('open')));

  // Instant search tables
  document.querySelectorAll('[data-table-search]').forEach(input=>{
    const target = document.querySelector(input.dataset.tableSearch);
    if(!target) return;
    input.addEventListener('input',()=>{
      const q=input.value.toLowerCase();
      target.querySelectorAll('tbody tr').forEach(tr=>{
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });
  });

  // Confirm delete
  document.querySelectorAll('[data-confirm]').forEach(el=>{
    el.addEventListener('click',e=>{
      if(!confirm(el.dataset.confirm || 'Confirmer cette action ?')) e.preventDefault();
    });
  });

  // Auto hide alerts
  setTimeout(()=>{ document.querySelectorAll('.alert-auto').forEach(a=>a.remove()); },4000);

  // POS quantity buttons delegated
  document.addEventListener('click',e=>{
    if(e.target.closest('[data-inc]')){ const inp=e.target.closest('.input-group').querySelector('input'); inp.value=Math.max(1, parseInt(inp.value||1)+1);}
    if(e.target.closest('[data-dec]')){ const inp=e.target.closest('.input-group').querySelector('input'); inp.value=Math.max(1, parseInt(inp.value||1)-1);}
  });
});

function toast(msg, type='success'){
  const c=document.querySelector('.toast-container')||(()=>{
    const d=document.createElement('div'); d.className='toast-container'; document.body.appendChild(d); return d;
  })();
  const t=document.createElement('div');
  t.className='app-toast '+(type==='error'?'error':'');
  t.innerHTML=`<i class="fa-solid ${type==='error'?'fa-circle-xmark text-danger':'fa-circle-check text-success'} mt-1"></i><div><div style="font-weight:700;font-size:13px">${type==='error'?'Erreur':'Succès'}</div><div style="font-size:13px;color:#475569">${msg}</div></div><button onclick="this.parentElement.remove()" class="btn btn-sm ms-auto"><i class="fa-solid fa-xmark"></i></button>`;
  c.appendChild(t);
  setTimeout(()=>t.remove(),4000);
}
function printInvoice(){ window.print(); }
