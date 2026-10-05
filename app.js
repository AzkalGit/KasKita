const rp=n=>'Rp '+n.toLocaleString('id-ID');
const data=[
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
const sum=(t,l=data)=>l.filter(x=>x.tipe==t).reduce((a,b)=>a+b.jml,0);
const tgl=s=>{const d=new Date(s);return d.getDate()+' '+bln[d.getMonth()]+' '+d.getFullYear()};
const $=s=>document.querySelector(s);
const page=document.body.dataset.page;
if(page){
const nav=[['dashboard','Dashboard','▦'],['transaksi','Transaksi','⇄'],['laporan','Laporan','▤']];
document.body.insertAdjacentHTML('afterbegin',`<div class="shell"><nav class="side"><div class="logo">Kas<b>Kita</b></div>${nav.map(n=>`<a href="${n[0]}.html" class="${n[0]==page?'on':''}"><span>${n[3]||n[2]}</span>${n[1]}</a>`).join('')}<div class="who"><div class="av">BR</div><div>Bima Raharja<small>Bendahara</small></div></div><a href="index.html" style="margin-top:8px">⎋ Keluar</a></nav><div class="main" id="main"></div></div>`);
$('#main').append(...document.querySelectorAll('body>.content>*'));
}
function bars(el){
const m=[6,7,8,9].map(i=>{const l=data.filter(x=>new Date(x.tgl).getMonth()==i+0);return[bln[i],sum('masuk',l),sum('keluar',l)]});
const mx=Math.max(...m.flat().filter(x=>typeof x=='number'));
el.innerHTML=m.map(r=>`<div class="g"><div class="pair"><i style="height:${r[1]/mx*100}%"></i><i class="o" style="height:${r[2]/mx*100}%"></i></div><span>${r[0]}</span></div>`).join('');
}
if(page=='dashboard'){
const mi=sum('masuk'),ke=sum('keluar');
$('#stats').innerHTML=`<div class="card stat hero"><small>Saldo kas</small><div>${rp(mi-ke)}</div></div><div class="card stat"><small>Total pemasukan</small><div class="in">${rp(mi)}</div></div><div class="card stat"><small>Total pengeluaran</small><div class="out">${rp(ke)}</div></div><div class="card stat"><small>Jumlah transaksi</small><div>${data.length}</div></div>`;
bars($('#bars'));
$('#recent').innerHTML=data.slice(0,5).map(x=>`<div class="tx"><div>${x.ket}<small>${tgl(x.tgl)} · ${x.kat}</small></div><strong class="${x.tipe=='masuk'?'in':'out'}">${x.tipe=='masuk'?'+':'−'}${rp(x.jml)}</strong></div>`).join('');
}
if(page=='transaksi'){
const draw=()=>{const q=$('#q').value.toLowerCase(),t=$('#tipe').value;
const l=data.filter(x=>(!t||x.tipe==t)&&x.ket.toLowerCase().includes(q));
$('#rows').innerHTML=l.length?l.map(x=>`<tr><td>${tgl(x.tgl)}</td><td>${x.ket}</td><td><span class="tag">${x.kat}</span></td><td class="${x.tipe=='masuk'?'in':'out'}">${x.tipe=='masuk'?'Pemasukan':'Pengeluaran'}</td><td><strong>${rp(x.jml)}</strong></td><td class="act"><button data-d="${x.id}" aria-label="Hapus">🗑</button></td></tr>`).join(''):'<tr><td colspan="6" class="empty">Belum ada transaksi yang cocok. Coba kata kunci lain atau tambah transaksi baru.</td></tr>'};
draw();
$('#q').oninput=$('#tipe').onchange=draw;
$('#rows').onclick=e=>{const id=e.target.dataset.d;if(id){data.splice(data.findIndex(x=>x.id==id),1);draw()}};
$('#tambah').onclick=()=>$('#dlg').showModal();
$('#batal').onclick=()=>$('#dlg').close();
$('#form').onsubmit=e=>{e.preventDefault();const f=new FormData(e.target);
data.unshift({id:Date.now(),tgl:f.get('tgl'),ket:f.get('ket'),kat:f.get('kat'),tipe:f.get('tipe'),jml:+f.get('jml')});
e.target.reset();$('#dlg').close();draw()};
}
if(page=='laporan'){
bars($('#bars'));
const kat={};data.filter(x=>x.tipe=='keluar').forEach(x=>kat[x.kat]=(kat[x.kat]||0)+x.jml);
const ke=sum('keluar');
$('#kat').innerHTML=Object.entries(kat).sort((a,b)=>b[1]-a[1]).map(([k,v])=>`<div class="cat"><div><span>${k}</span><span>${rp(v)}</span></div><u><i style="width:${v/ke*100}%"></i></u></div>`).join('');
$('#bulan').innerHTML=[9,8,7,6].map(i=>{const l=data.filter(x=>new Date(x.tgl).getMonth()==i);const a=sum('masuk',l),b=sum('keluar',l);return`<tr><td>${bln[i]} 2026</td><td class="in">${rp(a)}</td><td class="out">${rp(b)}</td><td><strong>${rp(a-b)}</strong></td></tr>`}).join('');
$('#cetak').onclick=()=>print();
}
if(page==null){document.querySelectorAll('form[data-go]').forEach(f=>f.onsubmit=e=>{e.preventDefault();location.href=f.dataset.go})}
