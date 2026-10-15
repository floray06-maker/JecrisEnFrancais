/* Nos réussites : application élève (FALC, téléphone). Aucune dépendance. */
(function () {
  'use strict';
  var CFG = window.NR_CFG || {};
  var root = document.getElementById('nr-app');
  if (!root) return;

  /* ------------------------------------------------------------ outils */
  var SVG = {
    suivant: '<svg viewBox="0 0 24 24"><path d="M5 12h13M12 5l7 7-7 7"/></svg>',
    retour: '<svg viewBox="0 0 24 24"><path d="M19 12H6M12 19l-7-7 7-7"/></svg>',
    son: '<svg viewBox="0 0 24 24"><path d="M4 9v6h4l5 4V5L8 9H4z"/><path d="M16 9a4 4 0 0 1 0 6M18.5 6.5a8 8 0 0 1 0 11"/></svg>',
    sortir: '<svg viewBox="0 0 24 24"><path d="M14 4h5v16h-5M10 8l-4 4 4 4M6 12h10"/></svg>',
    ok: '<svg viewBox="0 0 24 24"><path d="M4 12.5l5 5L20 6.5"/></svg>',
    micro: '<svg viewBox="0 0 24 24"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3M8 21h8"/></svg>',
    stop: '<svg viewBox="0 0 24 24"><rect x="6" y="6" width="12" height="12" rx="2" class="plein"/></svg>',
    lire: '<svg viewBox="0 0 24 24"><path d="M7 4l13 8-13 8z" class="plein"/></svg>',
    refaire: '<svg viewBox="0 0 24 24"><path d="M4 12a8 8 0 1 0 2.5-5.8M4 4v5h5"/></svg>',
    envoyer: '<svg viewBox="0 0 24 24"><path d="M12 19V5M5 12l7-7 7 7"/></svg>'
  };
  function el(tag, cls, html) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (html != null) e.innerHTML = html;
    return e;
  }
  function esc(s) {
    return String(s).replace(/[&<>"]/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]; });
  }
  /* typographie française à l'affichage */
  function fr(s) {
    return esc(s).replace(/ ([?!:;»])/g, ' $1').replace(/« /g, '« ').replace(/'/g, '’')
      .replace(/\b(M\.|Mme) /g, '$1 ').replace(/(\d) (heures|ans)/g, '$1 $2');
  }
  function img(n) { return CFG.img + n + '.png'; }
  function vibre(ms) { try { if (navigator.vibrate) navigator.vibrate(ms); } catch (e) {} }
  function secoue(e) { e.classList.remove('nr-secoue'); void e.offsetWidth; e.classList.add('nr-secoue'); vibre(90); }
  function btnIcone(svg, cls, label) {
    var b = el('button', 'nr-ibtn ' + (cls || ''), svg);
    b.type = 'button'; b.setAttribute('aria-label', label); return b;
  }

  /* ------------------------------------------------------------ voix (synthèse) */
  var voixFr = null, voixOk = 'speechSynthesis' in window;
  function chargeVoix() {
    if (!voixOk) return;
    var v = speechSynthesis.getVoices().filter(function (x) { return /^fr/i.test(x.lang); });
    voixFr = v.filter(function (x) { return /fr[-_]FR/i.test(x.lang); })[0] || v[0] || null;
  }
  if (voixOk) { chargeVoix(); speechSynthesis.onvoiceschanged = chargeVoix; }
  function dire(t) {
    if (!voixOk || !t) return;
    try {
      speechSynthesis.cancel();
      var u = new SpeechSynthesisUtterance(t.replace(/___/g, ' ').replace(/…/g, ''));
      u.lang = 'fr-FR'; u.rate = 0.85; if (voixFr) u.voice = voixFr;
      speechSynthesis.speak(u);
    } catch (e) {}
  }

  /* ------------------------------------------------------------ données et sauvegarde */
  var S = { prenom: '', cle: '', jeton: '', act: '', parcours: '', contenu: null, etat: null, voix: false, i: 0 };
  var LS = 'nr_eleve_' + CFG.activite;

  function api(chemin, corps, formulaire) {
    var opts = { method: 'POST', credentials: 'omit', cache: 'no-store', keepalive: !formulaire };
    if (formulaire) opts.body = corps;
    else { opts.headers = { 'Content-Type': 'application/json' }; opts.body = JSON.stringify(corps); }
    return fetch(CFG.rest + chemin, opts).then(function (r) {
      return r.json().then(function (j) { j._status = r.status; return j; });
    });
  }
  var minuteur = null;
  function sauver(vite) {
    if (!S.cle) return;
    S.etat.ecran = S.i; S.etat.maj = Date.now(); S.etat.gen = S.gen;
    try { localStorage.setItem(LS + '_etat_' + S.act + '_' + S.cle, JSON.stringify(S.etat)); } catch (e) {}
    majSuivant();
    clearTimeout(minuteur);
    var qui = { act: S.act, cle: S.cle, jeton: S.jeton, etat: S.etat };
    minuteur = setTimeout(function () { envoyerEtat(qui); }, vite ? 0 : 900);
  }
  var dernier = null;
  function envoyerEtat(qui) {
    qui = qui || dernier; if (!qui) return; dernier = qui;
    api('save', { activite: qui.act, cle: qui.cle, jeton: qui.jeton, etat: qui.etat })
      .then(function (j) { setReseau(j && j.ok); })
      .catch(function () { setReseau(false); setTimeout(function () { envoyerEtat(qui); }, 15000); });
  }
  var pastille = null;
  function setReseau(ok) { if (pastille) pastille.classList.toggle('nr-horsligne', !ok); }
  window.addEventListener('online', function () { envoyerEtat(); });
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden' && S.cle) { clearTimeout(minuteur); envoyerEtat({ act: S.act, cle: S.cle, jeton: S.jeton, etat: S.etat }); } });

  function item(ecranId, k) {
    var c = ecranId + ':' + k;
    if (!S.etat.items[c]) S.etat.items[c] = { v: null, ok: null, e: 0, a: false };
    return S.etat.items[c];
  }

  /* ------------------------------------------------------------ écran d'accueil */
  function accueil(msgErreur) {
    root.innerHTML = '';
    var w = el('div', 'nr-accueil');
    w.appendChild(el('img', 'nr-accueil-ico')).src = img('t_personne');
    var lab = el('label', 'nr-grand', 'Mon prénom');
    lab.setAttribute('for', 'nr-prenom');
    w.appendChild(lab);
    var ligne = el('div', 'nr-ligne-login');
    var inp = el('input', 'nr-champ nr-champ-login');
    inp.id = 'nr-prenom'; inp.type = 'text'; inp.autocomplete = 'off'; inp.setAttribute('autocapitalize', 'words');
    inp.setAttribute('autocorrect', 'off'); inp.spellcheck = false; inp.maxLength = 60;
    var go = btnIcone(SVG.suivant, 'nr-go', 'Entrer');
    ligne.appendChild(inp); ligne.appendChild(go); w.appendChild(ligne);
    var err = el('div', 'nr-erreur', '<span class="nr-q">?</span> Je demande au professeur.');
    err.hidden = !msgErreur; w.appendChild(err);
    root.appendChild(w);
    function entrer() {
      var p = inp.value.trim();
      if (!p) { secoue(inp); return; }
      go.disabled = true;
      api('login', { activite: CFG.activite, prenom: p }).then(function (j) {
        go.disabled = false;
        if (!j.ok && j.raison === 'aucune') { sansActivite(j); return; }
        if (!j.ok) {
          err.innerHTML = j._status === 200 || j._status === 429 ? '<span class="nr-q">?</span> Je demande au professeur.'
            : '<span class="nr-q">!</span> ' + j._status;
          err.hidden = false; secoue(inp); inp.select(); return;
        }
        demarrer(j);
      }).catch(function () { go.disabled = false; secoue(inp); err.innerHTML = '<span class="nr-q">!</span>'; err.hidden = false; });
    }
    go.onclick = entrer;
    inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') entrer(); });
    setTimeout(function () { inp.focus(); }, 200);
  }

  function demarrer(j) {
    S.prenom = j.prenom; S.cle = j.cle; S.jeton = j.jeton; S.parcours = j.parcours; S.act = j.activite;
    S.contenu = j.contenu; S.voix = !!j.voix; S.valide = j.valide || 0;
    var local = null;
    try { local = JSON.parse(localStorage.getItem(LS + '_etat_' + S.act + '_' + S.cle) || 'null'); } catch (e) {}
    S.gen = j.gen || '';
    if (local && local.gen !== S.gen) local = null; /* travail effacé ou parcours changé par le professeur */
    S.etat = j.etat && j.etat.items ? j.etat : (local && local.items ? local : { items: {}, champs: {}, oral: {}, ecran: 0 });
    if (local && local.items && j.etat && local.maj > (j.etat.maj || 0)) S.etat = local;
    S.etat.gen = S.gen;
    S.etat.champs = S.etat.champs || {}; S.etat.oral = S.etat.oral || {};
    try { localStorage.setItem(LS, JSON.stringify({ prenom: S.prenom })); } catch (e) {}
    retours(j.retours || [], bonjour);
  }

  /* ------------------------------------------------------------ pas d'activité diffusée */
  function sansActivite(j) {
    try { localStorage.setItem(LS, JSON.stringify({ prenom: j.prenom })); } catch (e) {}
    S.cle = j.cle;
    retours(j.retours || [], function () {
      S.cle = '';
      root.innerHTML = '';
      var w = el('div', 'nr-accueil');
      w.appendChild(el('div', 'nr-bonjour', 'Bonjour ' + esc(j.prenom)));
      w.appendChild(el('img', 'nr-accueil-ico')).src = img('t_horloge');
      w.appendChild(el('div', 'nr-titre-parcours', 'Pas d’activité aujourd’hui.'));
      var aut = btnIcone(SVG.sortir, 'nr-pas-moi', 'Sortir');
      aut.onclick = deconnexion;
      w.appendChild(aut);
      root.appendChild(w);
    });
  }

  /* ------------------------------------------------------------ retour du professeur, lu à l'élève */
  function retours(liste, ensuite) {
    if (!liste.length) { ensuite(); return; }
    var r = liste[0];
    root.innerHTML = '';
    var w = el('div', 'nr-accueil nr-retour');
    w.appendChild(el('img', 'nr-accueil-ico')).src = img('t_message');
    w.appendChild(el('div', 'nr-grand', 'Le professeur'));
    w.appendChild(el('div', 'nr-titre-parcours', fr(r.titre)));
    w.appendChild(el('img', 'nr-retour-etat')).src = img(r.valide === 1 ? 'reussi' : 't_horloge');
    var lu = r.valide === 1 ? 'Activité validée. ' : 'Je reprends l’activité. ';
    if (r.texte) w.appendChild(el('div', 'nr-retour-txt', fr(r.texte)));
    var ecoute = btnIcone(SVG.son, 'nr-go nr-go-grand', 'Écouter');
    ecoute.onclick = function () { dire(lu + (r.texte || '')); ok.disabled = false; };
    w.appendChild(ecoute);
    var ok = btnIcone(SVG.ok, 'nr-fait-btn nr-retour-ok', 'J’ai écouté');
    ok.disabled = voixOk; /* l'élève écoute au moins une fois */
    ok.onclick = function () {
      if (voixOk) speechSynthesis.cancel();
      api('lu', { activite: r.activite, cle: S.cle, jeton: r.jeton }).catch(function () {});
      retours(liste.slice(1), ensuite);
    };
    w.appendChild(ok);
    root.appendChild(w);
    dire(lu + (r.texte || '')); /* peut être bloqué par le navigateur : le bouton relance la lecture */
  }

  function bonjour() {
    root.innerHTML = '';
    var w = el('div', 'nr-accueil');
    w.appendChild(el('div', 'nr-bonjour', 'Bonjour ' + esc(S.prenom)));
    w.appendChild(el('div', 'nr-titre-parcours', fr(S.contenu.titre)));
    var d = el('div', 'nr-duree');
    d.appendChild(el('img')).src = img('t_horloge');
    d.appendChild(el('span', '', esc(S.contenu.duree || '')));
    w.appendChild(d);
    var go = btnIcone(SVG.suivant, 'nr-go nr-go-grand', 'Commencer');
    go.onclick = function () { S.i = Math.min(S.etat.ecran || 0, S.contenu.ecrans.length - 1); ecran(); };
    w.appendChild(go);
    var aut = btnIcone(SVG.sortir, 'nr-pas-moi', 'Ce n’est pas moi');
    aut.onclick = deconnexion;
    w.appendChild(aut);
    root.appendChild(w);
  }

  function deconnexion() {
    try { localStorage.removeItem(LS); } catch (e) {}
    S.cle = ''; accueil(false);
  }

  /* ------------------------------------------------------------ cadre d'un écran */
  var micro = null; /* enregistrement en cours, arrêté si l'élève quitte l'écran */
  var suivBtn = null, enregistreurs = [];

  /* ------------------------------------------------------------ activité obligatoire : chaque écran doit être fait */
  function itemFait(E, k) {
    var s = S.etat.items[E.id + ':' + k];
    if (!s) return false;
    return E.type === 'saisie' ? (s.ok === true || !!s.a) : (s.ok === true || s.ok === false);
  }
  function aFaire(E, it) { return !it.exemple && !it.bande; }
  function manques(E) {
    var m = [];
    if (E.type === 'choix' || E.type === 'multi' || E.type === 'saisie') {
      E.items.forEach(function (it, k) { if (aFaire(E, it) && !itemFait(E, k)) m.push(k); });
    } else if (E.type === 'carte') {
      E.champs.forEach(function (ch) { if (!String(S.etat.champs[E.id + ':' + ch.cle] || '').trim()) m.push(ch.cle); });
    } else if (E.type === 'redaction') {
      if (String(S.etat.champs[E.id + ':texte'] || '').trim().length < 10) m.push('texte');
    } else if (E.type === 'oral') {
      if (!S.etat.oral[E.id + ':a']) m.push('a');
      if ((E.enplus || E.enplus_oral) && !S.etat.oral[E.id + ':b']) m.push('b');
    }
    return m;
  }
  function complet(E) { return !manques(E).length; }
  function majSuivant() {
    if (!suivBtn || !S.contenu) return;
    var E = S.contenu.ecrans[S.i];
    var pret = complet(E) || enregistreurs.some(function (x) { return x.enAttente(); });
    suivBtn.classList.toggle('nr-bloque', !pret);
  }
  function montreManques(E) {
    var premier = null;
    manques(E).forEach(function (k) {
      var n = root.querySelector('[data-k="' + k + '"]');
      if (!n) return;
      n.classList.remove('nr-manque'); void n.offsetWidth; n.classList.add('nr-manque');
      if (!premier) premier = n;
    });
    if (premier && premier.scrollIntoView) premier.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function ecran() {
    enregistreurs = [];
    if (voixOk) speechSynthesis.cancel();
    if (micro) { try { micro(); } catch (e) {} micro = null; }
    var E = S.contenu.ecrans[S.i];
    root.innerHTML = '';
    var haut = el('div', 'nr-haut');
    var ret = btnIcone(SVG.retour, '', 'Écran précédent');
    ret.disabled = S.i === 0;
    ret.onclick = function () { if (S.i > 0) { S.i--; sauver(); ecran(); } };
    var barre = el('div', 'nr-barre');
    var n = S.contenu.ecrans.length;
    barre.innerHTML = '<span style="width:' + Math.round(100 * (S.i + 1) / n) + '%"></span>';
    pastille = el('span', 'nr-reseau'); pastille.title = 'connexion';
    var sortir = btnIcone(SVG.sortir, '', 'Sortir');
    sortir.onclick = function () { sauver(true); deconnexion(); };
    haut.appendChild(ret); haut.appendChild(barre); haut.appendChild(pastille); haut.appendChild(sortir);
    root.appendChild(haut);

    var corps = el('div', 'nr-corps');
    root.appendChild(corps);
    if (E.consigne) corps.appendChild(consigne(E));
    var rendu = { choix: choix, multi: multi, saisie: saisie, carte: carte, oral: oral, redaction: redaction, fin: fin }[E.type];
    if (rendu) rendu(E, corps);

    suivBtn = null;
    if (E.type !== 'fin') {
      var bas = el('div', 'nr-bas');
      var suiv = btnIcone(SVG.suivant, 'nr-suivant', 'Écran suivant');
      suivBtn = suiv;
      suiv.onclick = function () {
        if (suiv.disabled) return;
        /* lecture enregistrée mais pas envoyée : on l'envoie avant de changer d'écran */
        var attente = enregistreurs.filter(function (x) { return x.enAttente(); });
        if (attente.length) {
          suiv.disabled = true; suiv.classList.add('nr-attente');
          Promise.all(attente.map(function (x) { return x.envoyer(); })).then(function () {
            suiv.disabled = false; suiv.classList.remove('nr-attente'); suiv.onclick();
          });
          return;
        }
        if (!complet(E)) { secoue(suiv); montreManques(E); return; }
        S.i = Math.min(S.i + 1, n - 1); sauver(true); ecran();
      };
      bas.appendChild(suiv);
      root.appendChild(bas);
    }
    root.scrollTop = 0; window.scrollTo(0, 0);
    sauver();
  }

  function consigne(E) {
    var c = el('div', 'nr-consigne');
    c.appendChild(el('span', 'nr-num', String(E.num)));
    var i = el('img', 'nr-picto'); i.src = img(E.icone === 'coche' ? 't_coche' : E.icone === 'micro' ? 't_micro' : E.icone); i.alt = '';
    c.appendChild(i);
    c.appendChild(el('span', 'nr-consigne-txt', fr(E.consigne)));
    if (voixOk) {
      var s = btnIcone(SVG.son, 'nr-son', 'Écouter');
      s.onclick = function () { dire(E.consigne); };
      c.appendChild(s);
    }
    return c;
  }

  function tagExemple(box) { box.classList.add('nr-exemple'); box.appendChild(el('span', 'nr-tag-ex', 'Exemple')); }

  /* ------------------------------------------------------------ supports */
  function supports(E, corps) {
    var s = E.support || {};
    if (s.badges) {
      var g = el('div', 'nr-badges');
      s.badges.forEach(function (b) { g.appendChild(badge(b)); });
      corps.appendChild(g);
    }
    if (s.message) {
      var m = el('div', 'nr-message');
      m.innerHTML = '<div class="nr-msg-tete"><b>' + esc(s.message.de) + '</b><span>' + esc(s.message.heure) + '</span></div>' +
        '<div class="nr-msg-txt">' + fr(s.message.texte) + '</div>';
      corps.appendChild(m);
    }
    if (s.banque) {
      var bq = el('div', 'nr-banque');
      bq.appendChild(el('div', 'nr-banque-tete', 'Mots'));
      s.banque.forEach(function (w) { bq.appendChild(el('span', 'nr-chip', fr(w))); });
      corps.appendChild(bq);
    }
  }
  function badge(b, bande) {
    var d = el('div', bande ? 'nr-bande' : 'nr-badge');
    d.innerHTML = (bande ? '' : '<div class="nr-badge-nom">' + fr(b.nom) + '</div>') +
      '<div class="nr-badge-corps"><img alt="" src="' + img('t_' + b.img) + '"><div>' +
      (bande ? '<b>' + fr(b.nom) + '</b> ' : '') + '<div class="nr-badge-met">' + fr(b.metier) + '</div>' +
      '<div class="nr-badge-lieu">' + fr(b.lieu) + '</div></div></div>';
    return d;
  }

  /* ------------------------------------------------------------ choix unique */
  function choix(E, corps) {
    supports(E, corps);
    var liste = el('div', 'nr-liste nr-forme-' + (E.forme || 'ligne'));
    E.items.forEach(function (it, k) {
      var st = item(E.id, k);
      var row = el('div', 'nr-item');
      row.setAttribute('data-k', k);
      var invite = el('div', 'nr-invite');
      if (E.forme === 'lettres') invite.appendChild(el('span', 'nr-modele nr-modele-lettre', esc(it.modele)));
      else if (E.forme === 'dessins') invite.appendChild(el('span', 'nr-etiquette', esc(it.modele)));
      else invite.appendChild(el('span', 'nr-texte', phrase(it, st, k)));
      row.appendChild(invite);
      var opts = el('div', 'nr-options nr-n' + it.options.length);
      var boutons = [];
      it.options.forEach(function (o, j) {
        var b = el('button', 'nr-opt');
        b.type = 'button';
        if (E.forme === 'dessins') { b.innerHTML = '<img alt="" src="' + img('p_' + o) + '">'; b.setAttribute('aria-label', o); }
        else b.innerHTML = '<span>' + fr(o) + '</span>';
        b.appendChild(el('span', 'nr-case', SVG.ok));
        boutons.push(b); opts.appendChild(b);
        b.onclick = function () {
          if (it.exemple || st.ok !== null) return;
          st.e++; st.v = j;
          if (j === it.bonne) {
            st.ok = true; marque(); dire(it.dire || (E.forme === 'lettres' ? '' : ''));
          } else {
            b.classList.add('nr-faux'); secoue(b);
            setTimeout(function () { b.classList.remove('nr-faux'); }, 700);
            if (st.e >= Math.min(2, it.options.length - 1)) { st.ok = false; st.a = true; marque(); }
          }
          sauver();
        };
      });
      row.appendChild(opts);
      function marque() {
        boutons.forEach(function (bb, j) {
          bb.classList.toggle('nr-bon', j === it.bonne);
          bb.classList.toggle('nr-aide', j === it.bonne && st.a);
          bb.disabled = true;
        });
        var t = invite.querySelector('.nr-texte');
        if (t) t.innerHTML = phrase(it, st, k);
        row.classList.add('nr-fait');
      }
      if (it.exemple) { st.ok = true; st.v = it.bonne; tagExemple(row); marque(); }
      else if (st.ok !== null) marque();
      liste.appendChild(row);
    });
    corps.appendChild(liste);
  }
  function phrase(it, st, k) {
    var t = fr(it.texte || '');
    if (t.indexOf('___') < 0) return t;
    var rep = (st.ok !== null || it.exemple) ? '<b class="nr-rep">' + fr(it.options[it.bonne]) + '</b>' : '<span class="nr-trou"></span>';
    t = t.replace('___', rep);
    return t.charAt(0) === '<' ? t : t;
  }

  /* ------------------------------------------------------------ plusieurs réponses */
  function multi(E, corps) {
    var liste = el('div', 'nr-liste nr-forme-multi');
    E.items.forEach(function (it, k) {
      var st = item(E.id, k);
      if (!Array.isArray(st.v)) st.v = [];
      var row = el('div', 'nr-item');
      row.setAttribute('data-k', k);
      var inv = el('div', 'nr-invite');
      inv.appendChild(el('span', 'nr-modele', esc(it.modele)));
      row.appendChild(inv);
      var opts = el('div', 'nr-options nr-n4');
      var bs = [];
      it.options.forEach(function (o, j) {
        var b = el('button', 'nr-opt nr-opt-mot');
        b.type = 'button'; b.innerHTML = '<span>' + esc(o) + '</span>';
        b.appendChild(el('span', 'nr-case', SVG.ok));
        bs.push(b); opts.appendChild(b);
        b.onclick = function () {
          if (it.exemple || st.ok !== null || st.v.indexOf(j) >= 0) return;
          if (it.bonnes.indexOf(j) >= 0) {
            st.v.push(j); b.classList.add('nr-bon'); b.disabled = true;
            if (it.bonnes.every(function (x) { return st.v.indexOf(x) >= 0; })) { st.ok = true; fini(); }
          } else {
            st.e++; b.classList.add('nr-faux'); secoue(b);
            setTimeout(function () { b.classList.remove('nr-faux'); }, 700);
            if (st.e > 2) { st.a = true; st.ok = false; fini(); }
          }
          sauver();
        };
      });
      function fini() {
        bs.forEach(function (bb, j) {
          if (it.bonnes.indexOf(j) >= 0) { bb.classList.add('nr-bon'); if (st.a && st.v.indexOf(j) < 0) bb.classList.add('nr-aide'); }
          bb.disabled = true;
        });
        row.classList.add('nr-fait');
      }
      row.appendChild(opts);
      if (it.exemple) { st.v = it.bonnes.slice(); st.ok = true; tagExemple(row); fini(); }
      else {
        st.v.forEach(function (j) { bs[j].classList.add('nr-bon'); bs[j].disabled = true; });
        if (st.ok !== null) fini();
      }
      liste.appendChild(row);
    });
    corps.appendChild(liste);
  }

  /* ------------------------------------------------------------ saisie avec vérification */
  function plier(s, casse) {
    s = String(s || '').replace(/[’']/g, "'").replace(/[.,;!?]/g, ' ').replace(/\s+/g, ' ').trim();
    return casse ? s : s.toLowerCase();
  }
  function sansAccent(s) { return s.normalize('NFD').replace(/[̀-ͯ]/g, ''); }
  function juge(val, it, casse) {
    if (!val.trim()) return null;
    var v = plier(val, casse);
    for (var i = 0; i < it.reponses.length; i++) if (v === plier(it.reponses[i], casse)) return 'bon';
    var vl = sansAccent(plier(val, false));
    for (i = 0; i < it.reponses.length; i++) if (vl === sansAccent(plier(it.reponses[i], false))) return 'presque';
    return 'faux';
  }
  function attrsClavier(inp, mode) {
    inp.setAttribute('autocorrect', 'off'); inp.spellcheck = false; inp.autocomplete = 'off';
    inp.setAttribute('autocapitalize', mode === 'maj' ? 'characters' : mode === 'mot' ? 'words' : mode === 'phrase' ? 'sentences' : 'none');
    inp.setAttribute('enterkeyhint', 'next');
  }
  function saisie(E, corps) {
    supports(E, corps);
    var liste = el('div', 'nr-liste nr-forme-saisie');
    var champs = [];
    E.items.forEach(function (it, k) {
      if (it.bande) {
        var bd = badge(it.bande, true);
        if (it.exemple) bd.classList.add('nr-bande-ex');
        liste.appendChild(bd); return;
      }
      var st = item(E.id, k);
      var row = el('div', 'nr-item nr-item-saisie');
      row.setAttribute('data-k', k);
      if (it.gauche) row.appendChild(el('span', E.syllabes ? 'nr-syll' : (E.casse ? 'nr-modele' : 'nr-gauche'), fr(it.gauche)));
      if (it.gauche && !it.prefixe) row.appendChild(el('span', 'nr-fleche', SVG.suivant));
      var zone = el('div', 'nr-zone');
      if (it.prefixe) zone.appendChild(el('span', 'nr-prefixe', fr(it.prefixe)));
      var inp = el('input', 'nr-champ');
      inp.type = 'text'; attrsClavier(inp, E.clavier);
      inp.setAttribute('aria-label', it.gauche || it.prefixe || 'réponse');
      zone.appendChild(inp);
      var corr = el('div', 'nr-corrige'); zone.appendChild(corr);
      row.appendChild(zone);
      if (it.exemple) {
        inp.value = it.reponses[0] + (it.prefixe ? '.' : ''); inp.disabled = true; inp.classList.add('nr-bon');
        if (!liste.querySelector('.nr-tag-ex')) tagExemple(row); else row.classList.add('nr-exemple');
      } else {
        inp.value = st.v || '';
        if (st.ok === true) inp.classList.add(st.p ? 'nr-presque' : 'nr-bon');
        if (st.a) corr.innerHTML = fr(it.reponses[0]);
        inp.addEventListener('input', function () {
          st.v = inp.value; inp.classList.remove('nr-faux', 'nr-bon', 'nr-presque'); if (st.ok === true) st.ok = null; sauver();
        });
        champs.push({ it: it, st: st, inp: inp, corr: corr });
      }
      liste.appendChild(row);
    });
    corps.appendChild(liste);
    if (champs.length) {
      var v = el('button', 'nr-verifier', SVG.ok + '<span>Je vérifie.</span>');
      v.type = 'button';
      v.onclick = function () {
        champs.forEach(function (c) {
          var r = juge(c.inp.value, c.it, E.casse);
          c.inp.classList.remove('nr-faux', 'nr-bon', 'nr-presque');
          if (r === null) return;
          if (r === 'bon' || r === 'presque') {
            c.st.ok = true; c.st.p = r === 'presque'; c.inp.classList.add(r === 'bon' ? 'nr-bon' : 'nr-presque');
            if (r === 'presque') c.corr.innerHTML = fr(c.it.reponses[0]);
          } else {
            c.st.e++; c.st.ok = null; c.inp.classList.add('nr-faux'); secoue(c.inp);
            if (c.st.e >= 2) { c.st.a = true; c.corr.innerHTML = fr(c.it.reponses[0]); }
          }
        });
        sauver();
      };
      corps.appendChild(v);
    }
  }

  /* ------------------------------------------------------------ carte du lycée */
  function carte(E, corps) {
    function bloc(titre, valeurs) {
      var c = el('div', 'nr-carte');
      c.appendChild(el('div', 'nr-carte-tete', esc(E.modele.titre)));
      var b = el('div', 'nr-carte-corps');
      var ph = el('div', 'nr-carte-photo'); ph.appendChild(el('img')).src = img('t_personne'); b.appendChild(ph);
      var f = el('div', 'nr-carte-champs');
      E.champs.forEach(function (ch, k) {
        var l = el('label', 'nr-carte-ligne');
        if (!valeurs) l.setAttribute('data-k', ch.cle);
        l.appendChild(el('span', 'nr-carte-lab', esc(ch.label)));
        if (valeurs) l.appendChild(el('span', 'nr-carte-val', esc(valeurs[k])));
        else {
          var inp = el('input', 'nr-champ'); inp.type = 'text'; attrsClavier(inp, ch.clavier);
          var cle = E.id + ':' + ch.cle;
          inp.value = S.etat.champs[cle] || '';
          inp.addEventListener('input', function () {
            if (ch.clavier === 'maj') { var p = inp.selectionStart; inp.value = inp.value.toUpperCase(); try { inp.setSelectionRange(p, p); } catch (e) {} }
            S.etat.champs[cle] = inp.value; sauver();
          });
          l.appendChild(inp);
        }
        f.appendChild(l);
      });
      b.appendChild(f); c.appendChild(b);
      var w = el('div', 'nr-carte-bloc');
      if (valeurs) tagExemple(w);
      w.appendChild(c);
      return w;
    }
    corps.appendChild(bloc('', E.modele.valeurs));
    corps.appendChild(bloc('', null));
  }

  /* ------------------------------------------------------------ rédaction */
  function redaction(E, corps) {
    var cad = el('ol', 'nr-cadres');
    E.cadres.forEach(function (c) { cad.appendChild(el('li', '', fr(c))); });
    corps.appendChild(cad);
    var ta = el('textarea', 'nr-champ nr-texte-long');
    ta.setAttribute('data-k', 'texte');
    ta.rows = 8; ta.setAttribute('autocapitalize', 'sentences'); ta.setAttribute('autocorrect', 'off'); ta.spellcheck = false;
    ta.setAttribute('aria-label', 'Ma réponse');
    var cle = E.id + ':texte';
    ta.value = S.etat.champs[cle] || '';
    ta.addEventListener('input', function () { S.etat.champs[cle] = ta.value; sauver(); });
    corps.appendChild(ta);
  }

  /* ------------------------------------------------------------ lecture à voix haute */
  function pauses(t) {
    var h = fr(t);
    h = h.replace(/,/g, ', <b class="nr-p1">/</b>');
    h = h.replace(/([^M])\.(\s|$)/g, '$1. <b class="nr-p2">//</b>$2').replace(/( [!?])/g, '$1 <b class="nr-p2">//</b>');
    return h;
  }
  function oral(E, corps) {
    var a = el('div', 'nr-oral');
    if (E.mots) {
      var g = el('div', 'nr-mots');
      E.mots.forEach(function (m) { g.appendChild(el('span', 'nr-mot', esc(m))); });
      a.appendChild(g);
    }
    if (E.texte) {
      if (E.pauses) {
        a.appendChild(el('div', 'nr-legende',
          '<span><b>,</b> <b class="nr-p1">/</b></span><span><b>. ! ?</b> <b class="nr-p2">//</b></span>'));
      }
      a.appendChild(el('div', 'nr-lecture', E.pauses ? pauses(E.texte) : fr(E.texte)));
    }
    a.appendChild(enregistreur(E, 'a', E.texte || (E.mots || []).join(', ')));
    corps.appendChild(a);
    if (E.enplus || E.enplus_oral) {
      var b = el('div', 'nr-oral nr-enplus');
      b.appendChild(el('span', 'nr-tag-plus', 'EN PLUS'));
      if (E.enplus) E.enplus.forEach(function (p) { b.appendChild(el('div', 'nr-phrase', fr(p))); });
      if (E.enplus_oral) b.appendChild(el('div', 'nr-phrase nr-phrase-b', fr(E.enplus_oral)));
      b.appendChild(enregistreur(E, 'b', (E.enplus || []).join(' ')));
      corps.appendChild(b);
    }
  }

  function enregistreur(E, part, modele) {
    var cle = E.id + ':' + part;
    var w = el('div', 'nr-rec');
    w.setAttribute('data-k', part);
    var peut = S.voix && window.MediaRecorder && navigator.mediaDevices && navigator.mediaDevices.getUserMedia;
    var fait = btnIcone(SVG.ok, 'nr-fait-btn', 'C’est fait');
    function majFait() { fait.classList.toggle('nr-actif', !!S.etat.oral[cle]); }
    if (!peut) {
      /* repli : message vocal envoyé par l'élève hors de l'application */
      var p = el('div', 'nr-repli');
      p.appendChild(el('img')).src = img('t_micro');
      p.appendChild(el('img')).src = img('t_message');
      w.appendChild(p);
      fait.onclick = function () { S.etat.oral[cle] = S.etat.oral[cle] ? 0 : 1; majFait(); sauver(); };
      w.appendChild(fait); majFait();
      return w;
    }
    var rec = btnIcone(SVG.micro, 'nr-rec-btn', 'Enregistrer');
    var lire = btnIcone(SVG.lire, 'nr-rec-lire', 'Écouter');
    var refaire = btnIcone(SVG.refaire, 'nr-rec-refaire', 'Recommencer');
    var envoi = btnIcone(SVG.envoyer, 'nr-rec-envoi', 'Envoyer');
    var temps = el('span', 'nr-rec-temps', '');
    var audio = new Audio(); var blob = null, mr = null, morceaux = [], t0 = 0, chrono = null, flux = null, etatRec = 'vide', apresStop = null;
    enregistreurs.push({
      enAttente: function () { return etatRec === 'rec' || etatRec === 'envoi' || (etatRec === 'pret' && !!blob); },
      envoyer: function () {
        return new Promise(function (fin) {
          if (etatRec === 'rec') { apresStop = function () { envoyerBlob().then(fin); }; mr.stop(); }
          else if (etatRec === 'pret' && blob) envoyerBlob().then(fin);
          else if (etatRec === 'envoi') { var t = setInterval(function () { if (etatRec !== 'envoi') { clearInterval(t); fin(); } }, 300); }
          else fin();
        });
      }
    });
    w.appendChild(rec); w.appendChild(temps); w.appendChild(lire); w.appendChild(refaire); w.appendChild(envoi); w.appendChild(fait);
    function etat(x) {
      etatRec = x; majSuivant();
      w.setAttribute('data-etat', x);
      rec.hidden = !(x === 'vide' || x === 'rec');
      rec.innerHTML = x === 'rec' ? SVG.stop : SVG.micro;
      rec.classList.toggle('nr-rec-on', x === 'rec');
      temps.hidden = x !== 'rec';
      lire.hidden = !((x === 'pret' || x === 'envoye') && blob);
      refaire.hidden = !(x === 'pret' || x === 'envoye');
      envoi.hidden = x !== 'pret';
      fait.hidden = x !== 'envoye';
      fait.classList.toggle('nr-actif', x === 'envoye');
      w.classList.toggle('nr-attente', x === 'envoi');
    }
    if (S.etat.oral[cle]) etat('envoye'); else etat('vide');
    function stopFlux() { if (flux) flux.getTracks().forEach(function (t) { t.stop(); }); flux = null; }
    rec.onclick = function () {
      if (mr && mr.state === 'recording') { mr.stop(); return; }
      navigator.mediaDevices.getUserMedia({ audio: true }).then(function (s) {
        flux = s; morceaux = [];
        var type = ['audio/webm;codecs=opus', 'audio/mp4', 'audio/webm', 'audio/ogg'].filter(function (t) {
          return MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(t);
        })[0];
        var o = { audioBitsPerSecond: 32000 }; if (type) o.mimeType = type;
        try { mr = new MediaRecorder(s, o); } catch (e) { mr = new MediaRecorder(s); }
        micro = function () { if (mr && mr.state === 'recording') { mr.onstop = null; mr.stop(); } clearInterval(chrono); stopFlux(); };
        mr.ondataavailable = function (e) { if (e.data && e.data.size) morceaux.push(e.data); };
        mr.onstop = function () {
          clearInterval(chrono); stopFlux(); micro = null;
          blob = new Blob(morceaux, { type: mr.mimeType || 'audio/webm' });
          audio.src = URL.createObjectURL(blob); etat('pret');
          if (apresStop) { var f = apresStop; apresStop = null; f(); }
        };
        mr.start(); t0 = Date.now(); etat('rec');
        chrono = setInterval(function () {
          var s2 = Math.round((Date.now() - t0) / 1000);
          temps.textContent = s2 + ' s';
          if (s2 >= 120) mr.stop();
        }, 500);
      }).catch(function () { secoue(rec); S.voix = false; var n = enregistreur(E, part, modele); w.replaceWith(n); });
    };
    lire.onclick = function () { if (blob) { audio.currentTime = 0; audio.play(); } };
    refaire.onclick = function () { blob = null; temps.textContent = '0 s'; etat('vide'); };
    envoi.onclick = function () { envoyerBlob(); };
    function envoyerBlob() {
      if (!blob) return Promise.resolve(false);
      etat('envoi');
      var fd = new FormData();
      fd.append('activite', S.act); fd.append('cle', S.cle); fd.append('jeton', S.jeton); fd.append('partie', cle);
      fd.append('fichier', blob, 'voix.' + (/mp4/.test(blob.type) ? 'm4a' : /ogg/.test(blob.type) ? 'ogg' : 'webm'));
      return api('audio', fd, true).then(function (j) {
        if (j.ok) { S.etat.oral[cle] = 1; etat('envoye'); sauver(true); return true; }
        etat('pret'); secoue(envoi); return false;
      }).catch(function () { etat('pret'); secoue(envoi); return false; });
    }
    return w;
  }

  /* ------------------------------------------------------------ fin */
  function fin(E, corps) {
    var w = el('div', 'nr-fin');
    w.appendChild(el('img', 'nr-etoile')).src = img('reussi');
    w.appendChild(el('div', 'nr-grand', 'J’ai fini.'));
    var f = el('div', 'nr-visages');
    ['ok', 'moy', 'pas'].forEach(function (k) {
      var b = el('button', 'nr-visage'); b.type = 'button';
      b.setAttribute('aria-label', { ok: 'Je sais faire', moy: 'Un peu', pas: 'Pas encore' }[k]);
      b.innerHTML = '<img alt="" src="' + img('face_' + k) + '">';
      if (S.etat.autoeval === k) b.classList.add('nr-actif');
      b.onclick = function () {
        S.etat.autoeval = k; S.etat.fini = 1;
        Array.prototype.forEach.call(f.children, function (x) { x.classList.remove('nr-actif'); });
        b.classList.add('nr-actif'); envoye.hidden = false; prof.hidden = false; sauver(true);
      };
      f.appendChild(b);
    });
    w.appendChild(f);
    var envoye = el('div', 'nr-envoye', SVG.ok);
    envoye.hidden = !S.etat.fini;
    w.appendChild(envoye);
    var prof = el('div', 'nr-attente-prof');
    prof.appendChild(el('img')).src = img(S.valide === 1 ? 'reussi' : 't_horloge');
    prof.appendChild(el('span', '', S.valide === 1 ? 'Le professeur a validé.' : 'Le professeur regarde mon travail.'));
    prof.hidden = !S.etat.fini;
    w.appendChild(prof);
    corps.appendChild(w);
  }

  /* ------------------------------------------------------------ lancement */
  var memo = null;
  try { memo = JSON.parse(localStorage.getItem(LS) || 'null'); } catch (e) {}
  if (memo && memo.prenom) {
    api('login', { activite: CFG.activite, prenom: memo.prenom }).then(function (j) {
      if (j.ok) demarrer(j); else if (j.raison === 'aucune') sansActivite(j); else accueil(false);
    }).catch(function () { accueil(false); });
  } else accueil(false);

})();
