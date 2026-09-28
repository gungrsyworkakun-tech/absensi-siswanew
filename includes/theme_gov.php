<style>
/* =========================================================
   Tema institusional (navy + emas) — v2, lebih modern:
   shadow bertingkat bernuansa warna, efek glass di topbar,
   hover-lift & transisi halus, indikator dot pada status pill,
   animasi masuk yang lembut. Nama class dipertahankan sama
   persis dari versi sebelumnya (drop-in replacement).
   ========================================================= */
:root{
  --navy:#131A2E; --navy-2:#0B0F1D; --navy-3:#1C2540; --gold:#F4B740; --gold-soft:#FDF3DD;
  --bg:#F4F5F9; --surface:#FFFFFF; --line:#E7E9F1;
  --ink:#1C2233; --ink-soft:#6B7280;
  --brand:#131A2E; --brand-2:#0B0F1D; --brand-soft:#EEF0F8;
  --gps:#2563EB; --gps-soft:#EAF2FE;
  --good:#16A34A; --good-soft:#E7F6EC;
  --warn:#EA7A1B; --warn-soft:#FDEEE0;
  --bad:#DC2626;  --bad-soft:#FCEAEA;
  --info:#2563EB; --info-soft:#EAF2FE;

  --r-lg:16px; --r-md:12px; --r-sm:9px;
  --sh-sm:0 1px 2px rgba(19,26,46,.04), 0 1px 3px rgba(19,26,46,.06);
  --sh-md:0 4px 10px -2px rgba(19,26,46,.08), 0 8px 24px -8px rgba(19,26,46,.10);
  --sh-lg:0 12px 28px -6px rgba(19,26,46,.16), 0 4px 10px -4px rgba(19,26,46,.10);
  --ease:cubic-bezier(.4,0,.2,1);
}

*{ scrollbar-width:thin; scrollbar-color:#C7CBDA transparent; }
::-webkit-scrollbar{ width:8px; height:8px; }
::-webkit-scrollbar-thumb{ background:#C7CBDA; border-radius:20px; }
::-webkit-scrollbar-thumb:hover{ background:#A9AFC4; }

body{ background:var(--bg); color:var(--ink); font-family:'Inter',system-ui,sans-serif; }

@keyframes gvFadeUp{ from{ opacity:0; transform:translateY(8px);} to{ opacity:1; transform:translateY(0);} }
.gv-card,.gv-table-wrap,.gv-topline{ animation:gvFadeUp .35s var(--ease) both; }

/* ---- Topbar: efek glass ---- */
.topbar{
  background:linear-gradient(120deg, rgba(19,26,46,.98), rgba(11,15,29,.98)) !important;
  backdrop-filter:blur(10px);
  box-shadow:0 1px 0 rgba(255,255,255,.05), 0 6px 20px -8px rgba(0,0,0,.35);
  border-bottom:1px solid rgba(255,255,255,.06);
}

/* ---- Sidebar ---- */
.sidebar{ background:var(--navy) !important; border-right:none !important; }
.sidebar .nav-section-label{ color:rgba(255,255,255,.32) !important; letter-spacing:.09em; }
.sidebar .nav-link{
  color:rgba(255,255,255,.78) !important; border-radius:var(--r-sm) !important;
  transition:background .18s var(--ease), color .18s var(--ease), transform .15s var(--ease);
  position:relative;
}
.sidebar .nav-link i{
  color:rgba(255,255,255,.55) !important; transition:color .18s var(--ease), transform .18s var(--ease);
}
.sidebar .nav-link:hover{ background:rgba(255,255,255,.07) !important; transform:translateX(2px); }
.sidebar .nav-link:hover i{ transform:scale(1.08); }
.sidebar .nav-link.active{
  background:linear-gradient(90deg, rgba(244,183,64,.16), rgba(244,183,64,.05)) !important;
  color:var(--gold) !important; font-weight:700;
  box-shadow:inset 3px 0 0 var(--gold);
}
.sidebar .nav-link.active i{ color:var(--gold) !important; }
.sidebar .nav-link.gps-link.active,
.sidebar .nav-link.gps-link:hover{
  background:linear-gradient(90deg, rgba(37,99,235,.18), rgba(37,99,235,.05)) !important;
  color:#8FB6F7 !important; box-shadow:inset 3px 0 0 #2563EB;
}

/* ---- Header / breadcrumb ---- */
.gv-topline{ display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:8px; }
.gv-title{ font-size:1.08rem; font-weight:800; color:var(--ink); letter-spacing:-.01em; }
.gv-title small{ display:block; font-size:.68rem; font-weight:700; letter-spacing:.09em; text-transform:uppercase; color:var(--ink-soft); margin-bottom:3px; }
.gv-crumb{ font-size:.78rem; color:var(--ink-soft); background:var(--surface); border:1px solid var(--line); padding:6px 12px; border-radius:20px; box-shadow:var(--sh-sm); }
.gv-crumb a{ color:var(--ink-soft); text-decoration:none; transition:color .15s; }
.gv-crumb a:hover{ color:var(--navy); }
.gv-crumb b{ color:var(--ink); font-weight:700; }

.gv-section-label{
  font-size:.7rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:var(--navy);
  margin:24px 0 14px; padding-bottom:9px; border-bottom:2px solid var(--brand-soft); position:relative;
}
.gv-section-label::after{ content:""; position:absolute; left:0; bottom:-2px; width:44px; height:2px; background:var(--gold); }
.gv-section-label:first-of-type{ margin-top:0; }

/* ---- Kartu ---- */
.gv-card{
  background:var(--surface); border:1px solid var(--line); border-radius:var(--r-lg);
  padding:20px 22px; box-shadow:var(--sh-sm); transition:box-shadow .2s var(--ease), transform .2s var(--ease);
}

/* ---- Tombol ---- */
.gv-btn{
  background:linear-gradient(135deg, var(--navy-3), var(--navy-2));
  border:1px solid var(--navy-2); color:#fff; border-radius:var(--r-sm);
  font-weight:700; font-size:.85rem; padding:9px 18px; box-shadow:var(--sh-sm);
  transition:transform .15s var(--ease), box-shadow .2s var(--ease), background .2s var(--ease);
  display:inline-flex; align-items:center; gap:6px; justify-content:center;
}
.gv-btn:hover{ color:#fff; transform:translateY(-1px); box-shadow:var(--sh-md); background:linear-gradient(135deg,#20294A,var(--navy-2)); }
.gv-btn:active{ transform:translateY(0) scale(.98); }
.gv-btn-outline{
  background:#fff; border:1px solid var(--line); color:var(--ink); border-radius:var(--r-sm);
  font-weight:600; font-size:.85rem; padding:9px 18px;
  transition:background .18s var(--ease), border-color .18s var(--ease), transform .15s var(--ease);
  display:inline-flex; align-items:center; gap:6px; justify-content:center;
}
.gv-btn-outline:hover{ background:var(--brand-soft); border-color:#CBD1E6; color:var(--ink); transform:translateY(-1px); }
.gv-btn-outline:active{ transform:translateY(0) scale(.98); }

.form-control, .form-select{ border-radius:var(--r-sm); border-color:var(--line); font-size:.87rem; transition:border-color .15s, box-shadow .15s; }
.form-control:focus, .form-select:focus{ border-color:var(--navy); box-shadow:0 0 0 3px var(--brand-soft); }
.form-label{ font-size:.8rem; font-weight:600; color:var(--ink); }

/* ---- Tabel ---- */
.gv-table-wrap{ background:var(--surface); border:1px solid var(--line); border-radius:var(--r-lg); overflow:hidden; box-shadow:var(--sh-sm); }
.gv-table-wrap .table{ margin-bottom:0; }
.gv-table-wrap thead th{
  text-transform:uppercase; font-size:.67rem; letter-spacing:.07em; font-weight:800;
  color:var(--ink-soft); background:linear-gradient(180deg,#FAFBFD,#F4F5F9); border-bottom:1px solid var(--line); white-space:nowrap;
  padding-top:12px; padding-bottom:12px;
}
.gv-table-wrap td{ font-size:.85rem; vertical-align:middle; border-bottom:1px solid var(--line); position:relative; transition:background .15s; }
.gv-table-wrap tbody tr:last-child td{ border-bottom:none; }
.gv-table-wrap tbody tr{ transition:background .15s var(--ease); }
.gv-table-wrap tbody tr:hover{ background:#FAFBFF; }
.gv-table-wrap tbody tr:hover td:first-child::before{
  content:""; position:absolute; left:0; top:0; bottom:0; width:3px; background:var(--gold);
}

/* ---- Pill / badge dengan indikator titik ---- */
.gv-pill{ display:inline-flex; align-items:center; gap:5px; font-size:.68rem; font-weight:700; letter-spacing:.02em; padding:4px 11px 4px 9px; border-radius:20px; }
.gv-pill::before{ content:""; width:6px; height:6px; border-radius:50%; background:currentColor; flex-shrink:0; }
.gv-pill.good{ background:var(--good-soft); color:var(--good); }
.gv-pill.bad{ background:var(--bad-soft); color:var(--bad); }
.gv-pill.warn{ background:var(--warn-soft); color:var(--warn); }
.gv-pill.info{ background:var(--info-soft); color:var(--info); }
.gv-pill.neutral{ background:var(--brand-soft); color:var(--ink-soft); }

/* ---- Tombol aksi ikon ---- */
.gv-icon-btn{
  width:33px; height:33px; border-radius:var(--r-sm); display:inline-flex; align-items:center; justify-content:center;
  border:1px solid var(--line); color:var(--ink-soft); background:#fff;
  transition:background .18s var(--ease), color .18s var(--ease), transform .15s var(--ease), box-shadow .18s var(--ease);
}
.gv-icon-btn:hover{ background:var(--navy); color:#fff; border-color:var(--navy); transform:translateY(-2px); box-shadow:var(--sh-md); }
.gv-icon-btn.danger:hover{ background:var(--bad); color:#fff; border-color:var(--bad); box-shadow:0 8px 18px -6px rgba(220,38,38,.45); }

.gv-avatar-sm{ width:36px; height:36px; border-radius:50%; object-fit:cover; border:2px solid #fff; box-shadow:0 0 0 1px var(--line), var(--sh-sm); }

/* ---- Mini stat ---- */
.gv-mini-stat{
  background:var(--surface); border:1px solid var(--line); border-radius:var(--r-md); padding:15px; text-align:center;
  transition:transform .18s var(--ease), box-shadow .18s var(--ease);
}
.gv-mini-stat:hover{ transform:translateY(-3px); box-shadow:var(--sh-md); }
.gv-mini-stat .num{ font-size:1.55rem; font-weight:800; }
.gv-mini-stat .lbl{ font-size:.67rem; text-transform:uppercase; letter-spacing:.05em; color:var(--ink-soft); font-weight:700; }

/* ---- Profil ---- */
.gv-profile-photo{ width:120px; height:120px; border-radius:50%; object-fit:cover; border:4px solid var(--brand-soft); box-shadow:0 0 0 1px var(--line), var(--sh-md); }
.gv-info-row{ display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px dotted var(--line); font-size:.84rem; gap:10px; }
.gv-info-row:last-child{ border-bottom:none; }
.gv-info-row .k{ color:var(--ink-soft); flex-shrink:0; }
.gv-info-row .v{ font-weight:600; text-align:right; }
</style>