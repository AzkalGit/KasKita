/* KasKita — prototipe HTML/CSS/JS tanpa backend.
   Data disimpan di localStorage browser yang dipakai untuk presentasi. */
const rp=n=>'Rp '+Number(n||0).toLocaleString('id-ID');
const bln=['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
const blnFull=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
const $=s=>document.querySelector(s);
const page=document.body.dataset.page;
const LS=(k,v)=>{if(v===undefined){try{return JSON.parse(localStorage.getItem(k)||'null')}catch(e){return null}}try{localStorage.setItem(k,JSON.stringify(v));return true}catch(e){return false}};
const esc=t=>String(t??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const uid=()=>Date.now()*1000+Math.floor(Math.random()*1000);
const users=()=>LS('kaskitaUsers')||[];
const slug=s=>String(s||'').trim().toLowerCase().replace(/\s+/g,' ');
const orgKunci=n=>'kaskitaOrg:'+slug(n);
const orgIdKey=id=>'kaskitaOrgId:'+id;
const KAT_AWAL=['Iuran','Donasi','Acara','Perlengkapan'];
const newCode=()=>Math.random().toString(36).slice(2,6).toUpperCase()+Math.random().toString(36).slice(2,4).toUpperCase();
const getDirectory=()=>LS('kaskitaOrganizations')||[];
const setDirectory=d=>LS('kaskitaOrganizations',d);
function normal(o){
  o.saldoAwal=Number(o.saldoAwal||0);o.iuran=Number(o.iuran||0);o.infoBayar=o.infoBayar||'';
  o.kategori=Array.isArray(o.kategori)?o.kategori:[...KAT_AWAL];if(!o.kategori.includes('Iuran'))o.kategori.unshift('Iuran');
  o.anggota=Array.isArray(o.anggota)?o.anggota:[];o.tagihan=Array.isArray(o.tagihan)?o.tagihan:[];o.transaksi=Array.isArray(o.transaksi)?o.transaksi:[];
  return o;
}
function orgById(id){
  if(!id)return null;
  let o=LS(orgIdKey(id));
  if(!o){const meta=getDirectory().find(x=>x.id===id);if(meta)o=LS(orgKunci(meta.nama));}
  if(!o)return null;o=normal(o);o.id=o.id||id;LS(orgIdKey(id),o);return o;
}
function makeLegacyOrgId(name){return 'legacy-'+slug(name).replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'').slice(0,56)}
function migrateLegacy(){
  const us=users();let changed=false;let directory=getDirectory();
  us.forEach((u,index)=>{
    if(!u.id){u.id='user-'+uid()+'-'+index;changed=true}
    if(!Array.isArray(u.memberships)){
      u.memberships=[];
      if(u.org){
        const name=String(u.org).trim();const orgId=makeLegacyOrgId(name);let meta=directory.find(x=>x.id===orgId);
        if(!meta){meta={id:orgId,nama:name,createdAt:null,status:'aktif',joinCode:newCode(),creatorId:null,legacy:true};directory.push(meta)}
        let o=LS(orgIdKey(orgId))||LS(orgKunci(name))||{nama:name,transaksi:(LS('kaskitaData:'+(u.email||''))||[])};
        o=normal(o);o.id=orgId;o.nama=o.nama||name;o.status=o.status||meta.status||'aktif';o.joinCode=o.joinCode||meta.joinCode;
        const group=us.filter(x=>slug(x.org)===slug(name));
        let oldRole=u.peran;
        if(!oldRole)oldRole=group[0]===u?'bendahara':'anggota';
        const role=['bendahara','admin'].includes(oldRole)?'admin':oldRole==='editor'?'editor':'anggota';
        u.memberships.push({orgId,role});
        if(role==='admin'&&!meta.creatorId)meta.creatorId=u.id;
        const email=String(u.email||'').toLowerCase();
        if(email&&!o.anggota.some(a=>String(a.email||'').toLowerCase()===email))o.anggota.push({id:uid(),nama:u.nama||email,email,telp:'',aktif:true});
        LS(orgIdKey(orgId),o);changed=true;
      }
    }
  });
  if(changed)LS('kaskitaUsers',us);setDirectory(directory);
}
function makeDemoOrg(){
  const proof='data:image/svg+xml;charset=utf-8,'+encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="640" height="420"><rect width="100%" height="100%" fill="#f2f6f5"/><rect x="70" y="42" width="500" height="336" rx="20" fill="#fff" stroke="#cbded9" stroke-width="3"/><rect x="70" y="42" width="500" height="78" rx="20" fill="#0c4a46"/><text x="100" y="91" font-family="Arial" font-size="28" font-weight="700" fill="#fff">BUKTI TRANSFER (DEMO)</text><text x="105" y="166" font-family="Arial" font-size="20" fill="#12302d">Ke: Kas Organisasi BEM Fakultas</text><text x="105" y="211" font-family="Arial" font-size="20" fill="#12302d">Nominal: Rp 100.000</text><text x="105" y="256" font-family="Arial" font-size="20" fill="#12302d">Status: Menunggu verifikasi</text><text x="105" y="323" font-family="Arial" font-size="16" fill="#6b8480">GAMBAR FIKTIF UNTUK DEMONSTRASI</text></svg>');
  const org={id:'demo-bem-fakultas',nama:'BEM Fakultas Teknologi',createdAt:'2026-09-01',status:'aktif',joinCode:'BEM26KAS',creatorId:'demo-admin',saldoAwal:500000,iuran:100000,infoBayar:'Transfer ke rekening demo organisasi. Ini data fiktif untuk presentasi.',kategori:[...KAT_AWAL],anggota:[
    {id:300001,nama:'Nadia Putri',email:'admin@kaskita.demo',telp:'081200000001',aktif:true},
    {id:300002,nama:'Rafi Pratama',email:'editor@kaskita.demo',telp:'081200000002',aktif:true},
    {id:300003,nama:'Dimas Saputra',email:'anggota@kaskita.demo',telp:'081200000003',aktif:true},
    {id:300004,nama:'Sinta Hidayah',email:'sinta@example.demo',telp:'081200000004',aktif:true},
    {id:300005,nama:'Alya Nurdin',email:'alya@example.demo',telp:'081200000005',aktif:true}
  ],transaksi:[
    {id:200001,tgl:'2026-10-02',ket:'Sponsor kegiatan orientasi',kat:'Donasi',tipe:'masuk',jml:1500000},
    {id:200002,tgl:'2026-10-01',ket:'Sewa tempat rapat kerja',kat:'Acara',tipe:'keluar',jml:850000},
    {id:200003,tgl:'2026-09-25',ket:'Cetak spanduk seminar',kat:'Perlengkapan',tipe:'keluar',jml:420000},
    {id:200004,tgl:'2026-09-20',ket:'Konsumsi seminar',kat:'Acara',tipe:'keluar',jml:625000},
    {id:200005,tgl:'2026-09-05',ket:'Iuran September — Dimas Saputra',kat:'Iuran',tipe:'masuk',jml:100000,tagihanId:400001},
    {id:200006,tgl:'2026-10-02',ket:'Iuran Oktober — Alya Nurdin',kat:'Iuran',tipe:'masuk',jml:100000,tagihanId:400004}
  ],tagihan:[
    {id:400001,anggotaId:300003,bulan:'2026-09',nominal:100000,status:'lunas',tglLunas:'2026-09-05',via:'transfer',txId:200005},
    {id:400002,anggotaId:300003,bulan:'2026-10',nominal:100000,status:'belum'},
    {id:400003,anggotaId:300004,bulan:'2026-10',nominal:100000,status:'menunggu',bukti:{data:proof,nama:'bukti-transfer-demo.svg',tgl:'2026-10-03'}},
    {id:400004,anggotaId:300005,bulan:'2026-10',nominal:100000,status:'lunas',tglLunas:'2026-10-02',via:'transfer',txId:200006}
  ]};
  return org;
}
function ensureDemo(){
  const orgId='demo-bem-fakultas';let org=LS(orgIdKey(orgId));
  if(!org){org=makeDemoOrg();LS(orgIdKey(orgId),org)}
  let directory=getDirectory();let meta=directory.find(x=>x.id===orgId);
  if(!meta){meta={id:orgId,nama:org.nama,createdAt:org.createdAt||'2026-09-01',status:org.status||'aktif',joinCode:org.joinCode||'BEM26KAS',creatorId:'demo-admin'};directory.push(meta)}
  org.id=orgId;org.status=org.status||meta.status||'aktif';org.joinCode=org.joinCode||meta.joinCode||'BEM26KAS';org.createdAt=org.createdAt||meta.createdAt;LS(orgIdKey(orgId),normal(org));
  const defs=[
    {id:'demo-owner',nama:'Owner KAS Kita',email:'owner@kaskita.demo',pass:'KitaDemo2026!',platformRole:'owner',memberships:[]},
    {id:'demo-admin',nama:'Nadia Putri',email:'admin@kaskita.demo',pass:'KitaDemo2026!',memberships:[{orgId,role:'admin'}]},
    {id:'demo-editor',nama:'Rafi Pratama',email:'editor@kaskita.demo',pass:'KitaDemo2026!',memberships:[{orgId,role:'editor'}]},
    {id:'demo-member',nama:'Dimas Saputra',email:'anggota@kaskita.demo',pass:'KitaDemo2026!',memberships:[{orgId,role:'anggota'}]}
  ];
  let us=users();defs.forEach(def=>{let ix=us.findIndex(u=>String(u.email||'').toLowerCase()===def.email);if(ix<0)us.push(def);else{
    const old=us[ix];old.id=old.id||def.id;old.nama=old.nama||def.nama;old.pass=old.pass||def.pass;
    if(def.platformRole==='owner')old.platformRole='owner';
    if(!Array.isArray(old.memberships))old.memberships=[...(def.memberships||[])];
  }});
  LS('kaskitaUsers',us);setDirectory(directory);
}
migrateLegacy();ensureDemo();
const me=(()=>{
  const s=LS('kaskitaSession');if(!s)return null;
  const us=users();let u=us.find(x=>(s.userId&&x.id===s.userId)||String(x.email||'').toLowerCase()===String(s.email||'').toLowerCase());
  if(!u)return s;
  let orgId=s.currentOrgId;
  if(!u.platformRole&&Array.isArray(u.memberships)){
    if(!u.memberships.some(m=>m.orgId===orgId)){
      const oldOrg=s.org&&getDirectory().find(o=>slug(o.nama)===slug(s.org));
      orgId=oldOrg&&u.memberships.some(m=>m.orgId===oldOrg.id)?oldOrg.id:(u.memberships.length===1?u.memberships[0].orgId:null);
    }
  }else orgId=null;
  const next={...s,userId:u.id,nama:u.nama,email:u.email,platformRole:u.platformRole||null,currentOrgId:orgId};
  if(orgId){const m=(u.memberships||[]).find(x=>x.orgId===orgId);next.peran=m?m.role:null;const meta=getDirectory().find(x=>x.id===orgId);if(meta)next.org=meta.nama;}
  if(JSON.stringify(s)!==JSON.stringify(next))LS('kaskitaSession',next);
  return next;
})();
const meUser=me?users().find(u=>u.id===me.userId||String(u.email||'').toLowerCase()===String(me.email||'').toLowerCase()):null;
const isPlatformOwner=!!meUser&&meUser.platformRole==='owner';
const currentOrgId=me&&!isPlatformOwner?me.currentOrgId:null;
const currentMembership=meUser&&currentOrgId?(meUser.memberships||[]).find(m=>m.orgId===currentOrgId):null;
const role=currentMembership?currentMembership.role:(me?me.peran:null);
const isAdmin=role==='admin'||role==='bendahara';
const isEditor=role==='editor';
const isMember=role==='anggota'||role==='member';
const canReadFinance=isAdmin||isEditor;
const canEditTransactions=isAdmin||isEditor;
const bendahara=isAdmin; // alias untuk kompatibilitas dengan modul lama
let org=currentOrgId?orgById(currentOrgId):null;
if(page&&!me){location.replace('login.html');throw new Error('Silakan login terlebih dahulu');}
if(page==='owner'&&!isPlatformOwner){location.replace(me?'organisasi.html':'login.html');throw new Error('Khusus Owner/Dev');}
if(page!=='owner'&&isPlatformOwner&&page!=='organisasi'&&page){location.replace('owner.html');throw new Error('Akun Owner/Dev');}
if(page&&!['owner','organisasi'].includes(page)&&!currentMembership){location.replace('organisasi.html');throw new Error('Pilih organisasi terlebih dahulu');}
if(page&&!['owner','organisasi'].includes(page)&&org&&org.status==='nonaktif'){location.replace('organisasi.html');throw new Error('Organisasi sedang nonaktif');}
if(page&&isMember&&!['iuran','organisasi'].includes(page)){location.replace('iuran.html');throw new Error('Halaman ini bukan untuk Anggota');}
if(page&&isEditor&&['iuran','anggota','pengaturan'].includes(page)){location.replace('dashboard.html');throw new Error('Halaman ini khusus Admin');}
if(page==='organisasi'&&isPlatformOwner){location.replace('owner.html');throw new Error('Owner/Dev membuka panel platform');}
if(org){org=normal(org);if(!org.status)org.status='aktif';if(!org.joinCode)org.joinCode=getDirectory().find(x=>x.id===org.id)?.joinCode||newCode();}

/* ---------- penyimpanan dan mekanik organisasi ---------- */
const save=()=>{if(!me||!org)return true;
  try{const ok=LS(orgIdKey(org.id),org);if(!ok)throw new Error('quota');
    const directory=getDirectory(),meta=directory.find(x=>x.id===org.id);if(meta){meta.nama=org.nama;meta.status=org.status||'aktif';meta.joinCode=org.joinCode;meta.createdAt=org.createdAt||meta.createdAt;setDirectory(directory)}return true;
  }catch(e){alert('Penyimpanan browser penuh. Hapus beberapa lampiran (nota/bukti) lalu coba lagi.');return false}
};
const addMembership=(user,orgId,memberRole='anggota')=>{user.memberships=Array.isArray(user.memberships)?user.memberships:[];if(!user.memberships.some(m=>m.orgId===orgId))user.memberships.push({orgId,role:memberRole});};
const labelPeran=r=>({owner:'Owner/Dev',admin:'Admin',bendahara:'Admin',editor:'Editor',anggota:'Anggota',member:'Anggota'}[r]||'Belum ada peran');
const sum=(t,l=org?org.transaksi:[])=>l.filter(x=>x.tipe===t).reduce((a,b)=>a+Number(b.jml||0),0);
const saldo=()=>(org?org.saldoAwal:0)+sum('masuk')-sum('keluar');
const urut=l=>[...l].sort((a,b)=>String(b.tgl).localeCompare(String(a.tgl))||Number(b.id)-Number(a.id));
const data=org?org.transaksi:[];
const anggotaOleh=id=>org?.anggota.find(a=>a.id==id);
const namaAng=t=>(anggotaOleh(t.anggotaId)||{nama:'(anggota dihapus)'}).nama;
const saya=()=>org?.anggota.find(a=>a.email&&me&&a.email.toLowerCase()===String(me.email||'').toLowerCase());
const tagihanSaya=()=>{const a=saya();return a&&org?org.tagihan.filter(t=>t.anggotaId==a.id):[]};
const ST={belum:'Belum bayar',menunggu:'Menunggu verifikasi',lunas:'Lunas'};

/* ---------- tanggal & periode (format 'YYYY-MM-DD', diproses sebagai teks agar bebas zona waktu) ---------- */
const ym=s=>s.slice(0,7);
const tgl=s=>{const[y,m,d]=s.split('-');return +d+' '+bln[m-1]+' '+y};
const labelBulan=k=>{const[y,m]=k.split('-');return blnFull[m-1]+' '+y};
const labelPendek=k=>{const[y,m]=k.split('-');return bln[m-1]+' '+y};
const kini=()=>{const d=new Date();return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')};
const hariIni=()=>kini()+'-'+String(new Date().getDate()).padStart(2,'0');
const bulanAda=()=>[...new Set(data.map(x=>ym(x.tgl)))].sort();
const bulanTagihan=()=>[...new Set(org.tagihan.map(t=>t.bulan))].sort();
let periode=LS('kaskitaPeriode')||'semua'; // 'semua' atau 'YYYY-MM', dibagi antar halaman
const dalam=l=>periode=='semua'?l:l.filter(x=>ym(x.tgl)==periode);
const labelPeriode=()=>periode=='semua'?'Semua periode':labelBulan(periode);
function pilihPeriode(render,sumber=bulanAda){
const el=$('#periode');if(!el)return;
const ks=[...new Set([...sumber(),kini(),...(periode!='semua'?[periode]:[])])].sort().reverse();
el.innerHTML='<option value="semua">Semua periode</option>'+ks.map(k=>`<option value="${k}">${labelBulan(k)}</option>`).join('');
el.value=periode;
el.onchange=()=>{periode=el.value;LS('kaskitaPeriode',periode);render()};
}
function opsiBulan(el,pilih){ // pilihan bulan: 6 bulan lalu sampai 6 bulan ke depan
const[y0,m0]=kini().split('-').map(Number);const ks=[];
for(let i=-6;i<=6;i++){let m=m0+i,y=y0;while(m<1){m+=12;y--}while(m>12){m-=12;y++}ks.push(y+'-'+String(m).padStart(2,'0'))}
el.innerHTML=ks.map(k=>`<option value="${k}">${labelBulan(k)}</option>`).join('');el.value=pilih||kini()}

/* ---------- utilitas tampilan ---------- */
function toast(m){const t=document.createElement('div');t.className='toast';t.setAttribute('role','status');t.textContent=m;document.body.append(t);setTimeout(()=>t.remove(),3200)}
function konfirmasi(judul,info,ya,aksi){ // dialog konfirmasi bersama (butuh #dlgK di halaman)
$('#kJudul').textContent=judul;$('#kInfo').textContent=info;$('#kYa').textContent=ya;
const d=$('#dlgK');$('#kYa').onclick=()=>{d.close();aksi()};$('#kBatal').onclick=()=>d.close();d.showModal()}
function lihatGambar(src,judul){ // dialog pratinjau gambar (butuh #dlgImg)
$('#imgJudul').textContent=judul;$('#imgView').src=src;$('#imgTutup').onclick=()=>$('#dlgImg').close();$('#dlgImg').showModal()}
// Perkecil gambar (maks 900px, JPEG) supaya hemat penyimpanan browser.
function kecilkan(file,max=900,q=.72){return new Promise((ok,gagal)=>{
if(!file.type||!file.type.startsWith('image/'))return gagal(new Error('bukan gambar'));
const fr=new FileReader();fr.onerror=gagal;
fr.onload=()=>{const im=new Image();im.onerror=gagal;
im.onload=()=>{const s=Math.min(1,max/Math.max(im.width,im.height)),c=document.createElement('canvas');
c.width=Math.max(1,Math.round(im.width*s));c.height=Math.max(1,Math.round(im.height*s));
const g=c.getContext('2d');g.fillStyle='#fff';g.fillRect(0,0,c.width,c.height);g.drawImage(im,0,0,c.width,c.height);
ok(c.toDataURL('image/jpeg',q))};im.src=fr.result};
fr.readAsDataURL(file)})}
function unduhCSV(rows,nama){ // rows sudah termasuk baris judul
const c=v=>{let s=String(v);if(/^[=+\-@]/.test(s))s="'"+s;return '"'+s.replace(/"/g,'""')+'"'}; // cegah rumus di Excel
const blob=new Blob(['\ufeff'+rows.map(r=>r.map(c).join(',')).join('\r\n')],{type:'text/csv;charset=utf-8'});
const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=nama;document.body.append(a);a.click();a.remove();
setTimeout(()=>URL.revokeObjectURL(a.href),1000);
}
const csvTrx=l=>[['Tanggal','Keterangan','Kategori','Jenis','Jumlah (Rp)'],...l.map(x=>[x.tgl,x.ket,x.kat,x.tipe=='masuk'?'Pemasukan':'Pengeluaran',x.jml])];

/* ---------- kerangka halaman (sidebar mengikuti peran aktif) ---------- */
const ini=me?String(me.nama||'').split(' ').filter(Boolean).map(w=>w[0]).slice(0,2).join('').toUpperCase():'';
if(page&&page!=='owner'){
  const tunggu=isAdmin&&org?org.tagihan.filter(t=>t.status==='menunggu').length:0;
  let nav=[];
  if(isMember)nav=[['iuran','Iuran','◎'],['organisasi','Organisasi','▧']];
  else if(page==='organisasi'&&!currentMembership)nav=[['organisasi','Organisasi','▧']];
  else nav=[['dashboard','Dashboard','▦'],['transaksi','Transaksi','⇄'],...(isAdmin?[['iuran','Iuran','◎']]:[]),...(isAdmin?[['anggota','Anggota','☺']]:[]),['laporan','Laporan','▤'],...(isAdmin?[['pengaturan','Pengaturan','⚙']]:[]),['organisasi','Organisasi','▧']];
  const activeOrgName=org?org.nama:'Pilih organisasi';
  document.body.insertAdjacentHTML('afterbegin',`<div class="shell"><nav class="side"><div class="logo">Kas<b>Kita</b></div>${nav.map(n=>`<a href="${n[0]==='organisasi'?'organisasi.html':n[0]+'.html'}" class="${n[0]===page?'on':''}"><span>${n[2]}</span>${n[1]}${n[0]==='iuran'&&tunggu?`<em class="badge" title="Menunggu verifikasi">${tunggu}</em>`:''}</a>`).join('')}<div class="who"><div class="av">${esc(ini)}</div><div>${esc(me.nama)}<small>${esc(activeOrgName)} · ${esc(labelPeran(role))}</small></div></div><a href="login.html" id="keluar" style="margin-top:8px">⎋ Keluar</a></nav><div class="main" id="main"></div></div>`);
  const bodyContent=document.querySelector('body>.content');
  if(bodyContent){$('#main').append(...Array.from(bodyContent.children));bodyContent.remove();}
  $('#keluar').onclick=()=>{localStorage.removeItem('kaskitaSession')};
}

/* ---------- grafik batang: 6 bulan berakhir di periode terpilih (atau bulan terakhir yang punya data) ---------- */
function bars(el){
let[y,m]=(periode!='semua'?periode:(bulanAda().pop()||kini())).split('-').map(Number);
const ks=[];for(let i=0;i<6;i++){ks.unshift(y+'-'+String(m).padStart(2,'0'));if(--m==0){m=12;y--}}
const rows=ks.map(k=>{const l=data.filter(x=>ym(x.tgl)==k);return[k,sum('masuk',l),sum('keluar',l)]});
const mx=Math.max(1,...rows.flatMap(r=>[r[1],r[2]])); // minimal 1 supaya tidak NaN saat semua nol
el.innerHTML=rows.map((r,i)=>{const[yy,mm]=r[0].split('-');const lb=bln[mm-1]+(i==0||mm=='01'?" '"+yy.slice(2):'');
return`<div class="g" title="${labelBulan(r[0])}: masuk ${rp(r[1])}, keluar ${rp(r[2])}"><div class="pair"><i style="height:${r[1]/mx*100}%"></i><i class="o" style="height:${r[2]/mx*100}%"></i></div><span>${lb}</span></div>`}).join('');
return rows;
}

/* ---------- tampilan kosong ---------- */
function kosongkan(kosong){
$('#kosong').hidden=!kosong;$('#isi').hidden=kosong;$('#aksi').hidden=kosong;
if(kosong&&!canEditTransactions){$('#kosong .row').hidden=true;$('#kosong p').textContent='Belum ada transaksi yang dapat ditampilkan.'}
}
const bukaContoh=render=>{const b=$('#contoh');if(b)b.onclick=()=>{data.push(...JSON.parse(JSON.stringify(seed)));if(save()){pilihPeriode(render);render()}}};

/* ---------- dashboard ---------- */
if(page=='dashboard'){
$('#catat').hidden=!canEditTransactions;
const notif=()=>{let h='';
if(bendahara){const n=org.tagihan.filter(t=>t.status=='menunggu').length;if(n)h=`<a class="notif" href="iuran.html">${n} bukti pembayaran iuran menunggu verifikasi →</a>`}
else{const n=tagihanSaya().filter(t=>t.status=='belum').length;if(n)h=`<a class="notif" href="iuran.html">Kamu punya ${n} tagihan iuran yang belum dibayar →</a>`}
$('#notif').innerHTML=h};
const render=()=>{
kosongkan(!data.length);notif();
$('#sub').textContent='Ringkasan kas organisasi'+(periode=='semua'?', semua periode.':' '+labelBulan(periode)+'.');
if(!data.length)return;
const l=dalam(data),mi=sum('masuk',l),ke=sum('keluar',l),p=periode=='semua'?'semua periode':labelPendek(periode);
$('#stats').innerHTML=`<div class="card stat hero"><small>Saldo kas (semua periode)</small><div>${rp(saldo())}</div>${org.saldoAwal?`<small class="note">termasuk saldo awal ${rp(org.saldoAwal)}</small>`:''}</div><div class="card stat"><small>Pemasukan · ${p}</small><div class="in">${rp(mi)}</div></div><div class="card stat"><small>Pengeluaran · ${p}</small><div class="out">${rp(ke)}</div></div><div class="card stat"><small>Jumlah transaksi · ${p}</small><div>${l.length}</div></div>`;
bars($('#bars'));
$('#recent').innerHTML=l.length?urut(l).slice(0,5).map(x=>`<div class="tx"><div>${esc(x.ket)}<small>${tgl(x.tgl)} · ${esc(x.kat)}</small></div><strong class="${x.tipe=='masuk'?'in':'out'}">${x.tipe=='masuk'?'+':'−'}${rp(x.jml)}</strong></div>`).join(''):'<p class="empty">Belum ada transaksi pada periode ini.</p>';
};
pilihPeriode(render);bukaContoh(render);render();
}

/* ---------- transaksi ---------- */
if(page=='transaksi'){
const form=$('#form'),dlg=$('#dlg'),dlgH=$('#dlgHapus');
$('#tambah').hidden=!canEditTransactions;
const isiKat=()=>{const o=org.kategori.map(k=>`<option>${esc(k)}</option>`).join('');$('#fkat').innerHTML='<option value="">Semua kategori</option>'+o;form.elements.kat.innerHTML=o};
isiKat();
const tampil=()=>{const q=$('#q').value.trim().toLowerCase(),t=$('#tipe').value,k=$('#fkat').value,a=$('#dari').value,b=$('#sampai').value;
return urut(data).filter(x=>(!t||x.tipe==t)&&(!k||x.kat==k)&&(!a||x.tgl>=a)&&(!b||x.tgl<=b)&&x.ket.toLowerCase().includes(q))};
const aksi=x=>(x.nota?`<button data-n="${x.id}" aria-label="Lihat lampiran" title="Lihat lampiran">📎</button>`:'')+(canEditTransactions&&!x.tagihanId?`<button data-e="${x.id}" aria-label="Ubah">✎</button>`:'')+(isAdmin&&!x.tagihanId?`<button data-d="${x.id}" aria-label="Hapus">🗑</button>`:'');
const draw=()=>{const l=tampil();
$('#rows').innerHTML=l.length?l.map(x=>`<tr><td>${tgl(x.tgl)}</td><td>${esc(x.ket)}${x.tagihanId?'<small class="oto">Otomatis dari modul Iuran</small>':''}</td><td><span class="tag">${esc(x.kat)}</span></td><td class="${x.tipe=='masuk'?'in':'out'}">${x.tipe=='masuk'?'Pemasukan':'Pengeluaran'}</td><td><strong>${rp(x.jml)}</strong></td><td class="act">${aksi(x)}</td></tr>`).join(''):`<tr><td colspan="6" class="empty">${data.length?'Belum ada transaksi yang cocok dengan filter. Coba ubah kata kunci, kategori, atau rentang tanggal.':(bendahara?'Belum ada transaksi. Klik “+ Tambah transaksi” untuk mulai mencatat.':'Belum ada transaksi yang dicatat Admin atau Editor.')}</td></tr>`;
$('#ringkas').textContent=l.length?`${l.length} transaksi · masuk ${rp(sum('masuk',l))} · keluar ${rp(sum('keluar',l))}`:'';
$('#unduh').disabled=!l.length;
};
// satu dialog dipakai untuk tambah dan ubah
const buka=x=>{form.reset();const f=form.elements;form.dataset.id=x?x.id:'';
$('#jdl').textContent=x?'Ubah transaksi':'Catat transaksi';$('#simpan').textContent=x?'Simpan perubahan':'Simpan transaksi';
f.tipe.value=x?x.tipe:'masuk';f.tgl.value=x?x.tgl:hariIni();f.ket.value=x?x.ket:'';f.kat.value=x?x.kat:org.kategori[0];f.jml.value=x?x.jml:'';
$('#notaAda').hidden=!(x&&x.nota);
dlg.showModal()};
draw();
['#q','#tipe','#fkat','#dari','#sampai'].forEach(s=>{$(s).oninput=$(s).onchange=draw});
$('#reset').onclick=()=>{['#q','#tipe','#fkat','#dari','#sampai'].forEach(s=>$(s).value='');draw()};
$('#tambah').onclick=()=>buka();
$('#batal').onclick=()=>dlg.close();
form.onsubmit=async e=>{e.preventDefault();const f=new FormData(form),id=form.dataset.id;
const baru={tgl:f.get('tgl'),ket:f.get('ket').trim(),kat:f.get('kat'),tipe:f.get('tipe'),jml:Math.round(+f.get('jml'))};
if(!canEditTransactions)return toast('Peranmu tidak diizinkan mencatat transaksi.');
if(!baru.ket||!(baru.jml>0))return;
const file=f.get('nota');let nota=null;
if(file&&file.size){try{nota=await kecilkan(file)}catch(err){return toast('Lampiran harus berupa gambar (JPG, PNG, atau WebP).')}}
if(id){const x=data.find(x=>x.id==id);if(x){Object.assign(x,baru);if(nota)x.nota=nota;else if(f.get('hapusNota'))delete x.nota}}
else data.unshift({id:uid(),...baru,...(nota?{nota}:{})});
if(save()){dlg.close();draw()}};
// hapus dengan konfirmasi
let hapusId=null;
$('#rows').onclick=e=>{const b=e.target.closest('button');if(!b)return;
if(b.dataset.n){const x=data.find(x=>x.id==b.dataset.n);if(x&&x.nota)lihatGambar(x.nota,x.ket)}
if(b.dataset.e){const x=data.find(x=>x.id==b.dataset.e);if(x)buka(x)}
if(b.dataset.d){if(!isAdmin)return toast('Hanya Admin yang dapat menghapus transaksi.');const x=data.find(x=>x.id==b.dataset.d);if(!x||x.tagihanId)return;hapusId=x.id;$('#hapusInfo').textContent=`${x.ket} · ${rp(x.jml)} (${tgl(x.tgl)})`;dlgH.showModal()}};
$('#hapusBatal').onclick=()=>dlgH.close();
$('#hapusYa').onclick=()=>{if(!isAdmin)return toast('Hanya Admin yang dapat menghapus transaksi.');const i=data.findIndex(x=>x.id==hapusId&&!x.tagihanId);if(i>=0){data.splice(i,1);save()}dlgH.close();draw()};
$('#unduh').onclick=()=>unduhCSV(csvTrx(tampil()),'kaskita-transaksi.csv'); // sesuai filter yang aktif
}

/* ---------- anggota dan pengelolaan peran (Admin) ---------- */
if(page==='anggota'){
  const form=$('#form'),dlg=$('#dlg');
  const akunAnggota=a=>a.email?users().find(u=>String(u.email||'').toLowerCase()===a.email.toLowerCase()&&(u.memberships||[]).some(m=>m.orgId===org.id)):null;
  const peranAkun=a=>{const u=akunAnggota(a);const m=u&&(u.memberships||[]).find(x=>x.orgId===org.id);return m?m.role:null};
  const iuranAng=a=>{const ts=org.tagihan.filter(t=>t.anggotaId==a.id),tg=ts.filter(t=>t.status!=='lunas');
    return !ts.length?'<span class="mute">Belum ada tagihan</span>':tg.length?`<span class="out">Tunggakan ${rp(tg.reduce((s,t)=>s+t.nominal,0))}</span>`:'<span class="in">Lunas semua</span>'};
  const draw=()=>{
    const q=$('#q').value.trim().toLowerCase();
    const l=[...org.anggota].sort((a,b)=>a.nama.localeCompare(b.nama)).filter(a=>(a.nama+' '+(a.email||'')).toLowerCase().includes(q));
    $('#rows').innerHTML=l.length?l.map(a=>{
      const r=peranAkun(a),roleCell=r=== 'admin'?'<span class="tag">Admin</span>':r?`<select class="role-select" aria-label="Peran ${esc(a.nama)}" data-role-id="${a.id}"><option value="anggota" ${r==='anggota'?'selected':''}>Anggota</option><option value="editor" ${r==='editor'?'selected':''}>Editor</option></select>`:'<span class="mute">Belum bergabung</span>';
      const canRemove=r!=='admin';
      return `<tr><td><strong>${esc(a.nama)}</strong></td><td>${esc(a.email||'-')}${akunAnggota(a)?' <span class="tag">akun terhubung</span>':''}</td><td>${esc(a.telp||'-')}</td><td>${a.aktif===false?'<span class="st belum">Nonaktif</span>':'<span class="st lunas">Aktif</span>'}</td><td>${roleCell}</td><td>${iuranAng(a)}</td><td class="act"><button data-e="${a.id}" aria-label="Ubah">✎</button>${canRemove?`<button data-d="${a.id}" aria-label="Hapus">🗑</button>`:''}</td></tr>`
    }).join(''):`<tr><td colspan="7" class="empty">${org.anggota.length?'Tidak ada anggota yang cocok.':'Belum ada anggota. Bagikan kode undangan agar pengguna bergabung.'}</td></tr>`;
    $('#ringkas').textContent=org.anggota.length?`${org.anggota.length} kontak anggota · ${org.anggota.filter(a=>a.aktif!==false).length} aktif`:'';
  };
  if($('#kodeUndangan'))$('#kodeUndangan').textContent=org.joinCode||'—';
  if($('#salinKode'))$('#salinKode').onclick=async()=>{const code=org.joinCode||'';try{await navigator.clipboard.writeText(code);toast('Kode undangan disalin.')}catch(e){prompt('Salin kode undangan ini:',code)}};
  const buka=a=>{form.reset();const f=form.elements;form.dataset.id=a?a.id:'';
    $('#jdl').textContent=a?'Ubah anggota':'Tambah anggota';f.nama.value=a?a.nama:'';f.email.value=a?a.email||'':'';f.telp.value=a?a.telp||'':'';f.aktif.checked=a?a.aktif!==false:true;dlg.showModal()};
  draw();$('#q').oninput=draw;$('#tambah').onclick=()=>buka();$('#batal').onclick=()=>dlg.close();
  form.onsubmit=e=>{e.preventDefault();const f=form.elements,id=form.dataset.id;
    const b={nama:f.nama.value.trim(),email:f.email.value.trim().toLowerCase(),telp:f.telp.value.trim(),aktif:f.aktif.checked};
    if(!b.nama)return;
    if(b.email&&!/^\S+@\S+\.\S+$/.test(b.email))return toast('Format email belum benar.');
    if(b.email&&org.anggota.some(a=>a.email&&a.email.toLowerCase()===b.email&&String(a.id)!==String(id)))return toast('Email itu sudah dipakai anggota lain.');
    let a;if(id){a=anggotaOleh(id);if(a)Object.assign(a,b)}else{a={id:uid(),...b};org.anggota.push(a)}
    if(a&&b.email){const us=users(),u=us.find(x=>String(x.email||'').toLowerCase()===b.email);if(u){if(!Array.isArray(u.memberships))u.memberships=[];addMembership(u,org.id,'anggota');LS('kaskitaUsers',us)}}
    if(save()){dlg.close();draw();toast(id?'Perubahan disimpan.':'Anggota ditambahkan. Jika memiliki akun, keanggotaan juga ditautkan.')}
  };
  $('#rows').onclick=e=>{const bt=e.target.closest('button');if(!bt)return;
    if(bt.dataset.e){const a=anggotaOleh(bt.dataset.e);if(a)buka(a)}
    if(bt.dataset.d){const a=anggotaOleh(bt.dataset.d);if(!a)return;const r=peranAkun(a);if(r==='admin')return toast('Turunkan atau pindahkan kewenangan Admin terlebih dahulu.');const ts=org.tagihan.filter(t=>t.anggotaId==a.id);
      if(ts.some(t=>t.status==='lunas'))return toast('Anggota ini punya iuran lunas, tidak bisa dihapus. Nonaktifkan lewat tombol ubah.');
      konfirmasi('Hapus anggota?',`${a.nama}${ts.length?` dan ${ts.length} tagihannya yang belum lunas`:''} akan dihapus.`,'Ya, hapus',()=>{
        const us=users(),u=us.find(x=>String(x.email||'').toLowerCase()===String(a.email||'').toLowerCase());if(u)u.memberships=(u.memberships||[]).filter(m=>m.orgId!==org.id);LS('kaskitaUsers',us);
        org.anggota=org.anggota.filter(x=>x.id!=a.id);org.tagihan=org.tagihan.filter(t=>t.anggotaId!=a.id);save();draw();toast('Anggota dihapus.')
      });
    }
  };
  $('#rows').onchange=e=>{const sel=e.target.closest('select[data-role-id]');if(!sel)return;
    const a=anggotaOleh(sel.dataset.roleId);if(!a)return;const us=users(),u=us.find(x=>String(x.email||'').toLowerCase()===String(a.email||'').toLowerCase());if(!u)return;
    const m=(u.memberships||[]).find(x=>x.orgId===org.id);if(!m)return;
    if(m.role==='admin')return toast('Peran Admin tidak bisa diubah dari kontrol ini.');
    m.role=sel.value==='editor'?'editor':'anggota';LS('kaskitaUsers',us);draw();toast(`${a.nama} sekarang menjadi ${labelPeran(m.role)}.`)
  };
}

/* ---------- iuran ---------- */
const badge=t=>`<span class="st ${t.status}">${ST[t.status]}</span>`+(t.ditolak&&t.status=='belum'?`<small class="alasan">Ditolak: ${esc(t.ditolak)}</small>`:'');
function setLunas(t,via){ // tandai lunas + catat pemasukan otomatis
t.status='lunas';t.tglLunas=hariIni();t.via=via;delete t.ditolak;
const tx={id:uid(),tgl:hariIni(),ket:`Iuran ${labelBulan(t.bulan)} — ${namaAng(t)}`,kat:'Iuran',tipe:'masuk',jml:t.nominal,tagihanId:t.id};
data.unshift(tx);t.txId=tx.id}
function batalLunas(t){ // kembalikan ke belum/menunggu + hapus pemasukan otomatis
const i=data.findIndex(x=>x.id==t.txId);if(i>=0)data.splice(i,1);
t.status=t.bukti?'menunggu':'belum';delete t.txId;delete t.tglLunas;delete t.via}
if(page=='iuran'){
const bukaGambarTagihan=t=>t.bukti&&lihatGambar(t.bukti.data,`Bukti ${namaAng(t)} · ${labelBulan(t.bulan)}`);
if(bendahara){
$('#viewA').hidden=true;
const periodeList=()=>org.tagihan.filter(t=>periode=='semua'||t.bulan==periode);
const daftar=()=>{const q=$('#q').value.trim().toLowerCase(),s=$('#st').value;
return periodeList().filter(t=>(!s||t.status==s)&&namaAng(t).toLowerCase().includes(q)).sort((a,b)=>b.bulan.localeCompare(a.bulan)||namaAng(a).localeCompare(namaAng(b)))};
const draw=()=>{const p=periodeList(),l=daftar();
const tot=p.reduce((s,t)=>s+t.nominal,0),ok=p.filter(t=>t.status=='lunas').reduce((s,t)=>s+t.nominal,0);
$('#stats').innerHTML=`<div class="card stat"><small>Total tagihan</small><div>${rp(tot)}</div></div><div class="card stat"><small>Terkumpul (lunas)</small><div class="in">${rp(ok)}</div></div><div class="card stat"><small>Menunggu verifikasi</small><div>${p.filter(t=>t.status=='menunggu').length}</div></div><div class="card stat"><small>Belum lunas</small><div class="out">${rp(tot-ok)}</div></div>`;
$('#rows').innerHTML=l.length?l.map(t=>`<tr><td><strong>${esc(namaAng(t))}</strong></td><td>${labelBulan(t.bulan)}</td><td>${rp(t.nominal)}</td><td>${badge(t)}${t.via=='tunai'?'<small class="oto">Dibayar tunai</small>':''}</td><td><div class="aksi">${
t.status=='belum'?`<button class="btn sm ghost" data-a="tunai" data-id="${t.id}">Lunas tunai</button>`:
t.status=='menunggu'?`<button class="btn sm" data-a="periksa" data-id="${t.id}">Periksa bukti</button>`:
(t.bukti?`<button class="btn sm ghost" data-a="lihat" data-id="${t.id}">Lihat bukti</button>`:'')+`<button class="btn sm ghost" data-a="batal" data-id="${t.id}">Batalkan</button>`
}${t.status!='lunas'?`<button class="btn sm ghost" data-a="hapus" data-id="${t.id}">Hapus</button>`:''}</div></td></tr>`).join(''):`<tr><td colspan="5" class="empty">${org.tagihan.length?'Tidak ada tagihan yang cocok. Coba ganti periode atau filter.':'Belum ada tagihan. Tambahkan anggota, lalu klik “+ Buat tagihan”.'}</td></tr>`;
$('#unduh').disabled=!l.length;
};
const render=()=>draw();
pilihPeriode(render,bulanTagihan);draw();
$('#q').oninput=$('#st').onchange=draw;
// buat tagihan
const dT=$('#dlgTagihan'),fT=$('#fTagihan');
$('#buat').onclick=()=>{fT.reset();opsiBulan(fT.elements.bulan,periode!='semua'?periode:kini());fT.elements.nominal.value=org.iuran||'';
$('#tgInfo').textContent=`Tagihan dibuat untuk ${org.anggota.filter(a=>a.aktif!==false).length} anggota aktif yang belum punya tagihan di bulan itu.`;dT.showModal()};
$('#tgBatal').onclick=()=>dT.close();
fT.onsubmit=e=>{e.preventDefault();const bulan=fT.elements.bulan.value,nominal=Math.round(+fT.elements.nominal.value);
if(!(nominal>0))return;
const aktif=org.anggota.filter(a=>a.aktif!==false);
if(!aktif.length)return toast('Belum ada anggota aktif. Tambahkan anggota dulu di menu Anggota.');
let n=0;aktif.forEach(a=>{if(!org.tagihan.some(t=>t.anggotaId==a.id&&t.bulan==bulan)){org.tagihan.push({id:uid(),anggotaId:a.id,bulan,nominal,status:'belum'});n++}});
org.iuran=nominal;
if(!save())return;dT.close();periode=bulan;LS('kaskitaPeriode',periode);pilihPeriode(render,bulanTagihan);draw();
toast(n?`${n} tagihan ${labelBulan(bulan)} dibuat.`:`Semua anggota aktif sudah punya tagihan ${labelBulan(bulan)}.`)};
// periksa bukti
const dB=$('#dlgBukti');let cur=null;
const tutupB=()=>dB.close();
$('#bTutup').onclick=tutupB;
$('#bSetuju').onclick=()=>{if(!cur)return;setLunas(cur,'transfer');if(save()){tutupB();draw();toast('Iuran disetujui dan dicatat sebagai pemasukan.')}};
$('#bTolak').onclick=()=>{if(!cur)return;const al=$('#alasan').value.trim();if(!al){$('#alasan').focus();return toast('Isi alasan penolakan dulu.')}
cur.status='belum';cur.ditolak=al;cur.bukti=null;if(save()){tutupB();draw();toast('Bukti ditolak. Anggota bisa mengirim ulang.')}};
$('#rows').onclick=e=>{const b=e.target.closest('button[data-a]');if(!b)return;const t=org.tagihan.find(x=>x.id==b.dataset.id);if(!t)return;
const a=b.dataset.a,nm=`${namaAng(t)} · ${labelBulan(t.bulan)}`;
if(a=='lihat')bukaGambarTagihan(t);
if(a=='periksa'){cur=t;$('#bInfo').textContent=`${nm} · ${rp(t.nominal)}${t.bukti?` · diunggah ${tgl(t.bukti.tgl)}`:''}`;$('#bImg').src=t.bukti?t.bukti.data:'';$('#alasan').value='';dB.showModal()}
if(a=='tunai')konfirmasi('Tandai lunas (tunai)?',`${nm} · ${rp(t.nominal)} akan dicatat sebagai pemasukan kas.`,'Ya, lunas',()=>{setLunas(t,'tunai');if(save()){draw();toast('Iuran lunas dan dicatat sebagai pemasukan.')}});
if(a=='batal')konfirmasi('Batalkan status lunas?',`Pemasukan otomatis untuk ${nm} akan dihapus dari transaksi.`,'Ya, batalkan',()=>{batalLunas(t);if(save()){draw();toast('Status lunas dibatalkan.')}});
if(a=='hapus')konfirmasi('Hapus tagihan?',`Tagihan ${nm} akan dihapus.`,'Ya, hapus',()=>{org.tagihan=org.tagihan.filter(x=>x.id!=t.id);save();draw();toast('Tagihan dihapus.')})};
$('#unduh').onclick=()=>unduhCSV([['Anggota','Bulan','Nominal (Rp)','Status','Tanggal lunas','Cara bayar'],...daftar().map(t=>[namaAng(t),t.bulan,t.nominal,ST[t.status],t.tglLunas||'',t.via||''])],'kaskita-iuran-'+periode+'.csv');
}else{ // tampilan anggota: hanya tagihan milik sendiri
$('#viewB').hidden=true;$('#aksiB').hidden=true;
$('#info').textContent=org.infoBayar||'Tanyakan cara pembayaran kepada Admin.';
const dU=$('#dlgUp'),fU=$('#fUp');let cur=null;
const draw=()=>{const a=saya(),l=tagihanSaya().sort((x,y)=>y.bulan.localeCompare(x.bulan));
$('#rowsA').innerHTML=l.length?l.map(t=>`<tr><td>${labelBulan(t.bulan)}</td><td>${rp(t.nominal)}</td><td>${badge(t)}${t.status=='lunas'&&t.tglLunas?`<small class="oto">Lunas ${tgl(t.tglLunas)}</small>`:''}</td><td><div class="aksi">${
t.status=='belum'?`<button class="btn sm" data-a="up" data-id="${t.id}">${t.ditolak?'Kirim ulang bukti':'Upload bukti'}</button>`:(t.bukti?`<button class="btn sm ghost" data-a="lihat" data-id="${t.id}">Lihat bukti</button>`:'')
}</div></td></tr>`).join(''):`<tr><td colspan="4" class="empty">${a?'Belum ada tagihan iuran untukmu.':'Akunmu belum terdaftar sebagai anggota. Hubungi Admin.'}</td></tr>`};
draw();
$('#rowsA').onclick=e=>{const b=e.target.closest('button[data-a]');if(!b)return;const t=org.tagihan.find(x=>x.id==b.dataset.id);if(!t)return;
if(b.dataset.a=='lihat')bukaGambarTagihan(t);
if(b.dataset.a=='up'){cur=t;fU.reset();$('#upInfo').textContent=`${labelBulan(t.bulan)} · ${rp(t.nominal)}`;dU.showModal()}};
$('#upBatal').onclick=()=>dU.close();
fU.onsubmit=async e=>{e.preventDefault();const file=fU.elements.bukti.files[0];if(!file||!cur)return;
let img;try{img=await kecilkan(file)}catch(err){return toast('Bukti harus berupa gambar (JPG, PNG, atau WebP).')}
cur.bukti={data:img,nama:file.name,tgl:hariIni()};cur.status='menunggu';delete cur.ditolak;
if(save()){dU.close();draw();toast('Bukti terkirim. Menunggu verifikasi Admin.')}};
}
}

/* ---------- laporan ---------- */
if(page=='laporan'){
$('#cardTunggak').hidden=!bendahara;
const render=()=>{
kosongkan(!data.length);
if(!data.length)return;
const rows=bars($('#bars'));
$('#bt').textContent='Arus kas '+labelPendek(rows[0][0])+' – '+labelPendek(rows[rows.length-1][0]);
$('#kt').textContent='Dana dipakai untuk apa · '+labelPeriode();
const kat={};dalam(data).filter(x=>x.tipe=='keluar').forEach(x=>kat[x.kat]=(kat[x.kat]||0)+x.jml);
const ke=Object.values(kat).reduce((a,b)=>a+b,0);
$('#kat').innerHTML=ke?Object.entries(kat).sort((a,b)=>b[1]-a[1]).map(([k,v])=>`<div class="cat"><div><span>${esc(k)}</span><span>${rp(v)}</span></div><u><i style="width:${v/ke*100}%"></i></u></div>`).join(''):'<p class="empty">Belum ada pengeluaran pada periode ini.</p>';
const bs=bulanAda().reverse();let ta=0,tb=0;
let h=bs.map(k=>{const l=data.filter(x=>ym(x.tgl)==k),a=sum('masuk',l),b=sum('keluar',l);ta+=a;tb+=b;
return`<tr><td>${labelBulan(k)}</td><td class="in">${rp(a)}</td><td class="out">${rp(b)}</td><td><strong>${rp(a-b)}</strong></td></tr>`}).join('')+`<tr class="tot"><td>Total</td><td class="in">${rp(ta)}</td><td class="out">${rp(tb)}</td><td>${rp(ta-tb)}</td></tr>`;
if(org.saldoAwal)h+=`<tr class="tot"><td>Saldo awal</td><td colspan="2"></td><td>${rp(org.saldoAwal)}</td></tr><tr class="tot"><td>Saldo akhir</td><td colspan="2"></td><td>${rp(saldo())}</td></tr>`;
$('#bulan').innerHTML=h;
if(bendahara){ // tunggakan iuran per anggota (sesuai periode)
const tg={};org.tagihan.filter(t=>t.status!='lunas'&&(periode=='semua'||t.bulan==periode)).forEach(t=>{const k=namaAng(t);tg[k]=tg[k]||{n:0,s:0};tg[k].n++;tg[k].s+=t.nominal});
const e=Object.entries(tg).sort((a,b)=>b[1].s-a[1].s);
$('#tunggak').innerHTML=e.length?e.map(([k,v])=>`<div class="tx"><div>${esc(k)}<small>${v.n} bulan belum lunas</small></div><strong class="out">${rp(v.s)}</strong></div>`).join(''):'<p class="empty">Tidak ada tunggakan iuran pada periode ini.</p>'}
};
pilihPeriode(render);bukaContoh(render);render();
$('#cetak').onclick=()=>print();
$('#unduh').onclick=()=>unduhCSV(csvTrx(urut(dalam(data))),'kaskita-laporan-'+periode+'.csv');
}

/* ---------- pengaturan (bendahara) ---------- */
if(page=='pengaturan'){
const f=$('#fSet').elements;
f.infoBayar.value=org.infoBayar;f.iuran.value=org.iuran||'';f.saldoAwal.value=org.saldoAwal||'';
$('#fSet').onsubmit=e=>{e.preventDefault();
const sa=Math.round(+f.saldoAwal.value||0),iu=Math.round(+f.iuran.value||0);
if(sa<0||iu<0)return toast('Angka tidak boleh negatif.');
org.infoBayar=f.infoBayar.value.trim();org.iuran=iu;org.saldoAwal=sa;
if(save())toast('Pengaturan disimpan.')};
const dipakai=k=>data.filter(x=>x.kat==k).length;
const draw=()=>{$('#kats').innerHTML=org.kategori.map(k=>{const n=dipakai(k);
return`<div class="tx"><div>${esc(k)}<small>${n?n+' transaksi':'belum dipakai'}${k=='Iuran'?' · dipakai otomatis oleh modul Iuran':''}</small></div>${k=='Iuran'?'<span class="mute">tetap</span>':`<button class="btn sm ghost" data-k="${esc(k)}">Hapus</button>`}</div>`}).join('')};
draw();
$('#fKat').onsubmit=e=>{e.preventDefault();const v=$('#kBaru').value.trim();
if(!v)return;
if(v.length>30)return toast('Nama kategori maksimal 30 karakter.');
if(org.kategori.some(k=>k.toLowerCase()==v.toLowerCase()))return toast('Kategori itu sudah ada.');
org.kategori.push(v);if(save()){$('#kBaru').value='';draw();toast('Kategori ditambahkan.')}};
$('#kats').onclick=e=>{const b=e.target.closest('button[data-k]');if(!b)return;const k=b.dataset.k,n=dipakai(k);
if(n)return toast(`Kategori “${k}” dipakai ${n} transaksi, tidak bisa dihapus.`);
konfirmasi('Hapus kategori?',`Kategori “${k}” akan dihapus dari pilihan.`,'Ya, hapus',()=>{org.kategori=org.kategori.filter(x=>x!=k);save();draw();toast('Kategori dihapus.')})};
}

/* ---------- organisasi: pilih, buat, dan gabung ---------- */
if(page==='organisasi'){
  const list=$('#orgList'),fCreate=$('#formBuatOrg'),fJoin=$('#formGabungOrg');
  const organisasiSaya=()=>{const u=users().find(x=>x.id===me.userId);return (u?.memberships||[]).map(m=>({m,meta:getDirectory().find(o=>o.id===m.orgId)})).filter(x=>x.meta)};
  const drawOrg=()=>{
    const items=organisasiSaya();
    list.innerHTML=items.length?items.map(({m,meta})=>{
      const selected=meta.id===currentOrgId;
      const disabled=meta.status==='nonaktif';
      return `<div class="org-row"><div class="org-mark">${esc(meta.nama.split(/\s+/).slice(0,2).map(w=>w[0]).join('').toUpperCase())}</div><div class="org-details"><strong>${esc(meta.nama)}</strong><small>Dibuat ${esc(meta.createdAt||'—')} · Peran ${labelPeran(m.role)}</small><small><span class="st ${disabled?'belum':'lunas'}">${disabled?'Nonaktif':'Aktif'}</span>${selected?' <span class="tag">Sedang dipilih</span>':''}</small></div><button class="btn sm ${disabled?'ghost':''}" data-open-org="${esc(meta.id)}" ${disabled?'disabled':''}>${selected?'Buka lagi':'Buka organisasi'}</button></div>`
    }).join(''):'<div class="empty">Kamu belum tergabung ke organisasi mana pun. Buat organisasi baru atau masukkan kode undangan dari Admin.</div>';
  };
  drawOrg();
  list.onclick=e=>{const b=e.target.closest('button[data-open-org]');if(!b)return;const id=b.dataset.openOrg,meta=getDirectory().find(x=>x.id===id);if(!meta||meta.status==='nonaktif')return toast('Organisasi sedang nonaktif.');
    const us=users(),u=us.find(x=>x.id===me.userId),m=(u?.memberships||[]).find(x=>x.orgId===id);if(!m)return toast('Keanggotaan tidak ditemukan.');
    const session={...me,currentOrgId:id,org:meta.nama,peran:m.role};LS('kaskitaSession',session);location.href=m.role==='anggota'?'iuran.html':'dashboard.html';
  };
  fCreate.onsubmit=e=>{e.preventDefault();const nama=fCreate.elements.nama.value.trim();if(nama.length<3)return toast('Nama organisasi minimal 3 karakter.');
    if(getDirectory().some(o=>slug(o.nama)===slug(nama)))return toast('Nama organisasi tersebut sudah terdaftar di browser ini.');
    const id='org-'+uid(),code=newCode(),tanggal=new Date().toISOString().slice(0,10);const us=users(),u=us.find(x=>x.id===me.userId);if(!u)return toast('Akun tidak ditemukan. Silakan masuk kembali.');
    const o=normal({id,nama,createdAt:tanggal,status:'aktif',joinCode:code,creatorId:u.id,saldoAwal:0,iuran:0,infoBayar:'',kategori:[...KAT_AWAL],anggota:[{id:uid(),nama:u.nama,email:u.email,telp:'',aktif:true}],tagihan:[],transaksi:[]});
    addMembership(u,id,'admin');if(!LS('kaskitaUsers',us)||!LS(orgIdKey(id),o))return toast('Penyimpanan browser gagal. Coba kosongkan penyimpanan demo.');
    const d=getDirectory();d.push({id,nama,createdAt:tanggal,status:'aktif',joinCode:code,creatorId:u.id});setDirectory(d);
    LS('kaskitaSession',{...me,currentOrgId:id,org:nama,peran:'admin'});location.href='dashboard.html';
  };
  fJoin.onsubmit=e=>{e.preventDefault();const code=fJoin.elements.kode.value.trim().toUpperCase();if(!code)return;
    const meta=getDirectory().find(o=>String(o.joinCode||'').toUpperCase()===code);if(!meta)return toast('Kode undangan tidak ditemukan. Periksa kembali kode dari Admin.');
    if(meta.status==='nonaktif')return toast('Organisasi sedang nonaktif dan tidak menerima anggota.');
    const us=users(),u=us.find(x=>x.id===me.userId);if(!u)return toast('Akun tidak ditemukan. Silakan masuk kembali.');
    if((u.memberships||[]).some(m=>m.orgId===meta.id))return toast('Akunmu sudah tergabung di organisasi ini.');
    const o=orgById(meta.id);if(!o)return toast('Data organisasi tidak ditemukan.');
    addMembership(u,meta.id,'anggota');const email=String(u.email||'').toLowerCase();
    if(!o.anggota.some(a=>String(a.email||'').toLowerCase()===email))o.anggota.push({id:uid(),nama:u.nama,email:u.email,telp:'',aktif:true});
    if(!LS('kaskitaUsers',us))return toast('Keanggotaan tidak bisa disimpan.');
    LS(orgIdKey(meta.id),o);const session={...me,currentOrgId:meta.id,org:meta.nama,peran:'anggota'};LS('kaskitaSession',session);location.href='iuran.html';
  };
}

/* ---------- panel Owner/Dev: metadata organisasi saja ---------- */
if(page==='owner'){
  const tbody=$('#orgRows');
  const ringkas=()=>{
    const d=getDirectory();const active=d.filter(o=>o.status!=='nonaktif').length;
    $('#totalOrg').textContent=d.length;$('#totalAktif').textContent=active;$('#totalNonaktif').textContent=d.length-active;
    tbody.innerHTML=d.length?d.map(meta=>{
      const o=orgById(meta.id),count=o?o.anggota.length:0,disabled=meta.status==='nonaktif';
      return `<tr><td><strong>${esc(meta.nama)}</strong>${meta.id==='demo-bem-fakultas'?'<small class="oto">Organisasi demo</small>':''}</td><td>${esc(meta.createdAt||'—')}</td><td>${count}</td><td><span class="st ${disabled?'belum':'lunas'}">${disabled?'Nonaktif':'Aktif'}</span></td><td><button class="btn sm ${disabled?'':'ghost'}" data-toggle-org="${esc(meta.id)}">${disabled?'Aktifkan':'Nonaktifkan'}</button></td></tr>`;
    }).join(''):'<tr><td class="empty" colspan="5">Belum ada organisasi yang terdaftar di browser ini.</td></tr>';
  };
  ringkas();
  tbody.onclick=e=>{const b=e.target.closest('button[data-toggle-org]');if(!b)return;const d=getDirectory(),m=d.find(x=>x.id===b.dataset.toggleOrg);if(!m)return;
    m.status=m.status==='nonaktif'?'aktif':'nonaktif';setDirectory(d);const o=orgById(m.id);if(o){o.status=m.status;LS(orgIdKey(m.id),o)}ringkas();toast(`${m.nama} sekarang ${m.status==='aktif'?'aktif':'nonaktif'}.`);
  };
  $('#resetDemo').onclick=()=>{
    if(!confirm('Kembalikan organisasi demo BEM Fakultas Teknologi, transaksi, dan tagihannya ke data awal? Data organisasi lain tidak diubah.'))return;
    const o=makeDemoOrg();LS(orgIdKey(o.id),o);
    let d=getDirectory(),m=d.find(x=>x.id===o.id);if(m)Object.assign(m,{nama:o.nama,createdAt:o.createdAt,status:'aktif',joinCode:o.joinCode,creatorId:'demo-admin'});else d.push({id:o.id,nama:o.nama,createdAt:o.createdAt,status:'aktif',joinCode:o.joinCode,creatorId:'demo-admin'});setDirectory(d);
    const us=users();const setup=[['admin@kaskita.demo','demo-admin','Nadia Putri','admin'],['editor@kaskita.demo','demo-editor','Rafi Pratama','editor'],['anggota@kaskita.demo','demo-member','Dimas Saputra','anggota']];
    setup.forEach(([email,id,nama,r])=>{const u=us.find(x=>x.email===email);if(u){u.id=id;u.nama=nama;u.memberships=[{orgId:o.id,role:r}];}});LS('kaskitaUsers',us);ringkas();toast('Data demo dikembalikan ke kondisi awal.');
  };
  $('#keluarOwner').onclick=()=>{localStorage.removeItem('kaskitaSession');location.href='login.html'};
}

/* ---------- login dan pendaftaran akun ---------- */
if(page==null){
  if(me){location.replace(isPlatformOwner?'owner.html':'organisasi.html');}
  const f=document.querySelector('form[data-go]');
  if(f){
    const reg=location.pathname.endsWith('register.html');
    const err=document.createElement('p');err.style.cssText='color:#e0674f;font-weight:600;margin-bottom:12px;font-size:13px';err.hidden=true;f.querySelector('button').before(err);
    const show=m=>{err.textContent=m;err.hidden=false};
    f.onsubmit=e=>{e.preventDefault();const v=Object.fromEntries(new FormData(f));v.email=String(v.email||'').trim().toLowerCase();let u;
      if(reg){
        v.nama=String(v.nama||'').trim();if(v.nama.length<2)return show('Nama lengkap minimal 2 karakter.');
        if(String(v.pass||'').length<8)return show('Kata sandi minimal 8 karakter.');
        const us=users();if(us.some(x=>String(x.email||'').toLowerCase()===v.email))return show('Email sudah terdaftar. Silakan masuk.');
        u={id:'user-'+uid(),nama:v.nama,email:v.email,pass:v.pass,platformRole:null,memberships:[]};us.push(u);
        if(!LS('kaskitaUsers',us))return show('Penyimpanan browser tidak tersedia.');
        const session={userId:u.id,nama:u.nama,email:u.email,platformRole:null,currentOrgId:null};LS('kaskitaSession',session);location.href='organisasi.html';
      }else{
        u=users().find(x=>String(x.email||'').toLowerCase()===v.email&&x.pass===v.pass);
        if(!u)return show('Email atau kata sandi salah.');
        const memberships=u.memberships||[];
        const target=u.platformRole==='owner'?'owner.html':memberships.length===1?(memberships[0].role==='anggota'?'iuran.html':'dashboard.html'):'organisasi.html';
        const m=memberships.length===1?memberships[0]:null,meta=m&&getDirectory().find(o=>o.id===m.orgId);
        LS('kaskitaSession',{userId:u.id,nama:u.nama,email:u.email,platformRole:u.platformRole||null,currentOrgId:m?m.orgId:null,org:meta?.nama,peran:m?.role});
        location.href=target;
      }
    };
  }
}

