/* KasKita — prototipe front-end (tanpa backend).
   Data organisasi disimpan di localStorage (kunci kaskitaOrg:<nama organisasi>) dan dipakai bersama
   oleh semua akun di organisasi yang sama pada browser ini. Peran: bendahara (kelola) dan anggota (lihat + bayar iuran). */
const rp=n=>'Rp '+n.toLocaleString('id-ID');
// Data contoh: hanya dimuat jika bendahara menekan "Isi data contoh" di dashboard.
const seed=[
{id:1,tgl:'2026-10-02',ket:'Iuran anggota bulan Oktober',kat:'Iuran',tipe:'masuk',jml:2400000},
{id:2,tgl:'2026-10-01',ket:'Sewa tempat rapat kerja',kat:'Acara',tipe:'keluar',jml:850000},
{id:3,tgl:'2026-09-28',ket:'Donasi alumni',kat:'Donasi',tipe:'masuk',jml:1500000},
{id:4,tgl:'2026-09-25',ket:'Cetak spanduk seminar',kat:'Perlengkapan',tipe:'keluar',jml:420000},
{id:5,tgl:'2026-09-20',ket:'Konsumsi seminar nasional',kat:'Acara',tipe:'keluar',jml:1250000},
{id:6,tgl:'2026-09-12',ket:'Iuran anggota bulan September',kat:'Iuran',tipe:'masuk',jml:2300000},
{id:7,tgl:'2026-08-30',ket:'Pembelian alat tulis sekretariat',kat:'Perlengkapan',tipe:'keluar',jml:180000},
{id:8,tgl:'2026-08-15',ket:'Sponsor kegiatan bakti sosial',kat:'Donasi',tipe:'masuk',jml:2000000},
{id:9,tgl:'2026-08-10',ket:'Iuran anggota bulan Agustus',kat:'Iuran',tipe:'masuk',jml:2250000},
{id:10,tgl:'2026-07-22',ket:'Transport panitia',kat:'Acara',tipe:'keluar',jml:600000}];
const bln=['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
const blnFull=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
const $=s=>document.querySelector(s);
const page=document.body.dataset.page;
const LS=(k,v)=>v===undefined?JSON.parse(localStorage.getItem(k)||'null'):localStorage.setItem(k,JSON.stringify(v));
const users=()=>LS('kaskitaUsers')||[];
const me=LS('kaskitaSession');
const esc=t=>String(t).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const uid=()=>Date.now()*1000+Math.floor(Math.random()*1000);
if(page&&!me){location.replace('login.html');throw 'belum login'}

/* ---------- organisasi & peran ---------- */
const slug=s=>String(s||'').trim().toLowerCase().replace(/\s+/g,' ');
const orgKunci=n=>'kaskitaOrg:'+slug(n);
const KAT_AWAL=['Iuran','Donasi','Acara','Perlengkapan'];
function normal(o){ // lengkapi data lama (mis. dari Tahap 1) dengan field baru
o.saldoAwal=o.saldoAwal||0;o.iuran=o.iuran||0;o.infoBayar=o.infoBayar||'';
o.kategori=o.kategori||[...KAT_AWAL];if(!o.kategori.includes('Iuran'))o.kategori.unshift('Iuran');
o.anggota=o.anggota||[];o.tagihan=o.tagihan||[];o.transaksi=o.transaksi||[];return o}
// Ambil organisasi; jika belum ada, buat baru (data Tahap 1 milik pemilik lama ikut dipindahkan).
function siapkanOrg(nama,pemilikEmail){
let o=LS(orgKunci(nama));
if(!o){o={nama:nama.trim(),transaksi:(pemilikEmail&&LS('kaskitaData:'+pemilikEmail))||[]}}
o=normal(o);LS(orgKunci(nama),o);return o}
if(me&&!me.peran){ // sesi/akun dari Tahap 1: pemilik pertama organisasi menjadi bendahara
me.peran=LS(orgKunci(me.org))?'anggota':'bendahara';LS('kaskitaSession',me);
const us=users(),u=us.find(x=>x.email==me.email);if(u){u.peran=me.peran;LS('kaskitaUsers',us)}}
const org=me?siapkanOrg(me.org,me.email):null;
const bendahara=!!me&&me.peran=='bendahara';
if(page&&!bendahara&&['anggota','pengaturan'].includes(page)){location.replace('dashboard.html');throw 'khusus bendahara'}

/* ---------- data ---------- */
const data=org?org.transaksi:[];
const save=()=>{if(!me)return true;
try{LS(orgKunci(me.org),org);return true}
catch(e){alert('Penyimpanan browser penuh. Hapus beberapa lampiran (nota/bukti) lalu coba lagi.');location.reload();return false}};
const sum=(t,l=data)=>l.filter(x=>x.tipe==t).reduce((a,b)=>a+b.jml,0);
const saldo=()=>(org?org.saldoAwal:0)+sum('masuk')-sum('keluar');
const urut=l=>[...l].sort((a,b)=>b.tgl.localeCompare(a.tgl)||b.id-a.id); // terbaru di atas
const anggotaOleh=id=>org.anggota.find(a=>a.id==id);
const namaAng=t=>(anggotaOleh(t.anggotaId)||{nama:'(anggota dihapus)'}).nama;
const saya=()=>org.anggota.find(a=>a.email&&me&&a.email.toLowerCase()==me.email);
const tagihanSaya=()=>{const a=saya();return a?org.tagihan.filter(t=>t.anggotaId==a.id):[]};
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

/* ---------- kerangka halaman (sidebar menurut peran) ---------- */
const ini=me?me.nama.split(' ').filter(Boolean).map(w=>w[0]).slice(0,2).join('').toUpperCase():'';
if(page){
const tunggu=bendahara?org.tagihan.filter(t=>t.status=='menunggu').length:0;
const nav=[['dashboard','Dashboard','▦'],['transaksi','Transaksi','⇄'],['iuran','Iuran','◎'],...(bendahara?[['anggota','Anggota','☺']]:[]),['laporan','Laporan','▤'],...(bendahara?[['pengaturan','Pengaturan','⚙']]:[])];
document.body.insertAdjacentHTML('afterbegin',`<div class="shell"><nav class="side"><div class="logo">Kas<b>Kita</b></div>${nav.map(n=>`<a href="${n[0]}.html" class="${n[0]==page?'on':''}"><span>${n[2]}</span>${n[1]}${n[0]=='iuran'&&tunggu?`<em class="badge" title="Menunggu verifikasi">${tunggu}</em>`:''}</a>`).join('')}<div class="who"><div class="av">${esc(ini)}</div><div>${esc(me.nama)}<small>${esc(me.org)} · ${bendahara?'Bendahara':'Anggota'}</small></div></div><a href="login.html" id="keluar" style="margin-top:8px">⎋ Keluar</a></nav><div class="main" id="main"></div></div>`);
$('#main').append(...document.querySelectorAll('body>.content>*'));
$('#keluar').onclick=()=>localStorage.removeItem('kaskitaSession');
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
if(kosong&&!bendahara){$('#kosong .row').hidden=true;$('#kosong p').textContent='Bendahara belum mencatat transaksi.'}
}
const bukaContoh=render=>{const b=$('#contoh');if(b)b.onclick=()=>{data.push(...JSON.parse(JSON.stringify(seed)));if(save()){pilihPeriode(render);render()}}};

/* ---------- dashboard ---------- */
if(page=='dashboard'){
$('#catat').hidden=!bendahara;
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
$('#tambah').hidden=!bendahara;
const isiKat=()=>{const o=org.kategori.map(k=>`<option>${esc(k)}</option>`).join('');$('#fkat').innerHTML='<option value="">Semua kategori</option>'+o;form.elements.kat.innerHTML=o};
isiKat();
const tampil=()=>{const q=$('#q').value.trim().toLowerCase(),t=$('#tipe').value,k=$('#fkat').value,a=$('#dari').value,b=$('#sampai').value;
return urut(data).filter(x=>(!t||x.tipe==t)&&(!k||x.kat==k)&&(!a||x.tgl>=a)&&(!b||x.tgl<=b)&&x.ket.toLowerCase().includes(q))};
const aksi=x=>(x.nota?`<button data-n="${x.id}" aria-label="Lihat lampiran" title="Lihat lampiran">📎</button>`:'')+(bendahara&&!x.tagihanId?`<button data-e="${x.id}" aria-label="Ubah">✎</button><button data-d="${x.id}" aria-label="Hapus">🗑</button>`:'');
const draw=()=>{const l=tampil();
$('#rows').innerHTML=l.length?l.map(x=>`<tr><td>${tgl(x.tgl)}</td><td>${esc(x.ket)}${x.tagihanId?'<small class="oto">Otomatis dari modul Iuran</small>':''}</td><td><span class="tag">${esc(x.kat)}</span></td><td class="${x.tipe=='masuk'?'in':'out'}">${x.tipe=='masuk'?'Pemasukan':'Pengeluaran'}</td><td><strong>${rp(x.jml)}</strong></td><td class="act">${aksi(x)}</td></tr>`).join(''):`<tr><td colspan="6" class="empty">${data.length?'Belum ada transaksi yang cocok dengan filter. Coba ubah kata kunci, kategori, atau rentang tanggal.':(bendahara?'Belum ada transaksi. Klik “+ Tambah transaksi” untuk mulai mencatat.':'Belum ada transaksi yang dicatat bendahara.')}</td></tr>`;
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
if(b.dataset.d){const x=data.find(x=>x.id==b.dataset.d);if(!x)return;hapusId=x.id;$('#hapusInfo').textContent=`${x.ket} · ${rp(x.jml)} (${tgl(x.tgl)})`;dlgH.showModal()}};
$('#hapusBatal').onclick=()=>dlgH.close();
$('#hapusYa').onclick=()=>{const i=data.findIndex(x=>x.id==hapusId);if(i>=0){data.splice(i,1);save()}dlgH.close();draw()};
$('#unduh').onclick=()=>unduhCSV(csvTrx(tampil()),'kaskita-transaksi.csv'); // sesuai filter yang aktif
}

/* ---------- anggota (bendahara) ---------- */
if(page=='anggota'){
const form=$('#form'),dlg=$('#dlg');
const punyaAkun=a=>a.email&&users().some(u=>u.email==a.email.toLowerCase()&&slug(u.org)==slug(me.org));
const iuranAng=a=>{const ts=org.tagihan.filter(t=>t.anggotaId==a.id),tg=ts.filter(t=>t.status!='lunas');
return !ts.length?'<span class="mute">Belum ada tagihan</span>':tg.length?`<span class="out">Tunggakan ${rp(tg.reduce((s,t)=>s+t.nominal,0))}</span>`:'<span class="in">Lunas semua</span>'};
const draw=()=>{const q=$('#q').value.trim().toLowerCase();
const l=[...org.anggota].sort((a,b)=>a.nama.localeCompare(b.nama)).filter(a=>(a.nama+' '+(a.email||'')).toLowerCase().includes(q));
$('#rows').innerHTML=l.length?l.map(a=>`<tr><td><strong>${esc(a.nama)}</strong></td><td>${esc(a.email||'-')}${punyaAkun(a)?' <span class="tag">punya akun</span>':''}</td><td>${esc(a.telp||'-')}</td><td>${a.aktif===false?'<span class="st belum">Nonaktif</span>':'<span class="st lunas">Aktif</span>'}</td><td>${iuranAng(a)}</td><td class="act"><button data-e="${a.id}" aria-label="Ubah">✎</button><button data-d="${a.id}" aria-label="Hapus">🗑</button></td></tr>`).join(''):`<tr><td colspan="6" class="empty">${org.anggota.length?'Tidak ada anggota yang cocok.':'Belum ada anggota. Klik “+ Tambah anggota”, atau minta anggota mendaftar dengan nama organisasi yang sama.'}</td></tr>`;
$('#ringkas').textContent=org.anggota.length?`${org.anggota.length} anggota · ${org.anggota.filter(a=>a.aktif!==false).length} aktif`:'';
};
const buka=a=>{form.reset();const f=form.elements;form.dataset.id=a?a.id:'';
$('#jdl').textContent=a?'Ubah anggota':'Tambah anggota';
f.nama.value=a?a.nama:'';f.email.value=a?a.email||'':'';f.telp.value=a?a.telp||'':'';f.aktif.checked=a?a.aktif!==false:true;dlg.showModal()};
draw();
$('#q').oninput=draw;
$('#tambah').onclick=()=>buka();
$('#batal').onclick=()=>dlg.close();
form.onsubmit=e=>{e.preventDefault();const f=form.elements,id=form.dataset.id;
const b={nama:f.nama.value.trim(),email:f.email.value.trim().toLowerCase(),telp:f.telp.value.trim(),aktif:f.aktif.checked};
if(!b.nama)return;
if(b.email&&!/^\S+@\S+\.\S+$/.test(b.email))return toast('Format email belum benar.');
if(b.email&&org.anggota.some(a=>a.email&&a.email.toLowerCase()==b.email&&a.id!=id))return toast('Email itu sudah dipakai anggota lain.');
if(id){const a=anggotaOleh(id);if(a)Object.assign(a,b)}else org.anggota.push({id:uid(),...b});
if(save()){dlg.close();draw();toast(id?'Perubahan disimpan.':'Anggota ditambahkan.')}};
$('#rows').onclick=e=>{const bt=e.target.closest('button');if(!bt)return;
if(bt.dataset.e){const a=anggotaOleh(bt.dataset.e);if(a)buka(a)}
if(bt.dataset.d){const a=anggotaOleh(bt.dataset.d);if(!a)return;const ts=org.tagihan.filter(t=>t.anggotaId==a.id);
if(ts.some(t=>t.status=='lunas'))return toast('Anggota ini punya iuran lunas, tidak bisa dihapus. Nonaktifkan lewat tombol ubah.');
konfirmasi('Hapus anggota?',`${a.nama}${ts.length?` dan ${ts.length} tagihannya yang belum lunas`:''} akan dihapus.`,'Ya, hapus',()=>{
org.anggota=org.anggota.filter(x=>x.id!=a.id);org.tagihan=org.tagihan.filter(t=>t.anggotaId!=a.id);save();draw();toast('Anggota dihapus.')})}};
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
$('#info').textContent=org.infoBayar||'Tanyakan cara pembayaran kepada bendahara.';
const dU=$('#dlgUp'),fU=$('#fUp');let cur=null;
const draw=()=>{const a=saya(),l=tagihanSaya().sort((x,y)=>y.bulan.localeCompare(x.bulan));
$('#rowsA').innerHTML=l.length?l.map(t=>`<tr><td>${labelBulan(t.bulan)}</td><td>${rp(t.nominal)}</td><td>${badge(t)}${t.status=='lunas'&&t.tglLunas?`<small class="oto">Lunas ${tgl(t.tglLunas)}</small>`:''}</td><td><div class="aksi">${
t.status=='belum'?`<button class="btn sm" data-a="up" data-id="${t.id}">${t.ditolak?'Kirim ulang bukti':'Upload bukti'}</button>`:(t.bukti?`<button class="btn sm ghost" data-a="lihat" data-id="${t.id}">Lihat bukti</button>`:'')
}</div></td></tr>`).join(''):`<tr><td colspan="4" class="empty">${a?'Belum ada tagihan iuran untukmu.':'Akunmu belum terdaftar sebagai anggota. Hubungi bendahara.'}</td></tr>`};
draw();
$('#rowsA').onclick=e=>{const b=e.target.closest('button[data-a]');if(!b)return;const t=org.tagihan.find(x=>x.id==b.dataset.id);if(!t)return;
if(b.dataset.a=='lihat')bukaGambarTagihan(t);
if(b.dataset.a=='up'){cur=t;fU.reset();$('#upInfo').textContent=`${labelBulan(t.bulan)} · ${rp(t.nominal)}`;dU.showModal()}};
$('#upBatal').onclick=()=>dU.close();
fU.onsubmit=async e=>{e.preventDefault();const file=fU.elements.bukti.files[0];if(!file||!cur)return;
let img;try{img=await kecilkan(file)}catch(err){return toast('Bukti harus berupa gambar (JPG, PNG, atau WebP).')}
cur.bukti={data:img,nama:file.name,tgl:hariIni()};cur.status='menunggu';delete cur.ditolak;
if(save()){dU.close();draw();toast('Bukti terkirim. Menunggu verifikasi bendahara.')}};
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

/* ---------- login & daftar ---------- */
if(page==null){
const f=document.querySelector('form[data-go]');
if(f){
const reg=location.pathname.endsWith('register.html');
const err=document.createElement('p');err.style.cssText='color:#e0674f;font-weight:600;margin-bottom:12px;font-size:13px';err.hidden=true;f.querySelector('button').before(err);
const show=m=>{err.textContent=m;err.hidden=false};
f.onsubmit=e=>{e.preventDefault();const v=Object.fromEntries(new FormData(f));v.email=v.email.trim().toLowerCase();let u;
if(reg){
if(v.pass.length<8)return show('Kata sandi minimal 8 karakter.');
const us=users();
if(us.some(x=>x.email==v.email))return show('Email sudah terdaftar. Silakan masuk.');
const pemilik=us.find(x=>slug(x.org)==slug(v.org));
let o=LS(orgKunci(v.org));
if(o||pemilik){ // organisasi sudah ada -> bergabung sebagai anggota
if(!o){o=siapkanOrg(v.org,pemilik.email);if(!pemilik.peran)pemilik.peran='bendahara'} // pindahkan data Tahap 1 pemilik lama
u={nama:v.nama.trim(),org:o.nama,email:v.email,pass:v.pass,peran:'anggota'};
const ada=o.anggota.find(a=>a.email&&a.email.toLowerCase()==v.email); // sudah didaftarkan bendahara -> tautkan
if(!ada)o.anggota.push({id:uid(),nama:u.nama,email:v.email,telp:'',aktif:true});
LS(orgKunci(v.org),o);
}else{ // organisasi baru -> pendaftar menjadi bendahara
o=siapkanOrg(v.org.trim());
u={nama:v.nama.trim(),org:o.nama,email:v.email,pass:v.pass,peran:'bendahara'};
}
LS('kaskitaUsers',[...us,u]);
}else{
const us=users();u=us.find(x=>x.email==v.email&&x.pass==v.pass);
if(!u)return show('Email atau kata sandi salah.');
if(!u.peran){u.peran=LS(orgKunci(u.org))?'anggota':'bendahara';LS('kaskitaUsers',us)}
}
LS('kaskitaSession',{nama:u.nama,org:u.org,email:u.email,peran:u.peran});location.href=f.dataset.go}
}}
