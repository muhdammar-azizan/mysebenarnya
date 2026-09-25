/* SEBENARNYA.MY — Clarification Requests (Agency ⇄ MCMC, with MCMC-mediated agency consultation).
   MCMC is always the intermediary: only MCMC can invite another agency ("consulted agency") into a thread. The consulted agency can read
   the thread and send advice, but cannot change the case, close the thread, or contact the owning agency outside the thread. Shared store persisted in localStorage so both portals see the same threads.
   Thread status: 'open' (awaiting MCMC) · 'answered' (MCMC responded, awaiting agency) · 'closed' (resolved / withdrawn / auto-closed). */
(function () {
  var KEY = 'sb_clarify_v2', EV = 'sb-clarify-change';
  var AGENCY = 'Ministry of Health (MOH)';
  var TOPICS = ['Jurisdiction / scope', 'Missing or unclear evidence', 'Source verification', 'Submitter follow-up', 'Related or duplicate cases', 'Other'];

  var SEED = [
    { id: 'CLR-2026-0015', inquiryId: 'bnm-31', agency: 'Bank Negara Malaysia (BNM)', topic: 'Jurisdiction / scope', priority: 'Urgent', status: 'open',
      createdAt: '22 Sep 2026, 09:18', updatedAt: '22 Sep 2026, 11:02', unreadAgency: false, unreadMcmc: false,
      inquiry: { title: '"Health insurance refund" SMS asking for MyKad details', category: 'Financial Scam', dateAssigned: '19 Sep 2026', dateSubmitted: '17 Sep 2026',
        description: 'An SMS claiming to be from "KKM Refund Unit" tells recipients they are owed a medical insurance refund of RM 480 and asks them to reply with their MyKad number and bank account details.',
        mcmcNotes: 'Likely phishing — confirm whether any legitimate refund scheme exists before public advisory.', evidence: ['sms_screenshot.jpg', 'sender_number.png'] },
      consults: [{ agency: AGENCY, question: 'Please confirm whether MOH (KKM) runs any medical insurance refund programme, and whether MOH ever contacts the public via SMS asking for MyKad or bank details.',
        invitedBy: 'Siti Rahman', invitedAt: '22 Sep 2026, 11:02', status: 'pending', unread: true }],
      messages: [
        { from: 'agency', author: 'Encik Hafiz Karim', role: 'BNM Agency Staff', at: '22 Sep 2026, 09:18', files: [],
          text: 'The SMS uses the name "KKM Refund Unit". Before we classify this as a banking phishing case, can MCMC confirm with MOH whether any genuine medical insurance refund programme exists?' },
        { from: 'system', kind: 'invite', agency: AGENCY, author: 'Siti Rahman', role: 'MCMC Staff', at: '22 Sep 2026, 11:02', files: [],
          text: 'Siti Rahman (MCMC) invited Ministry of Health (MOH) to consult on this request.' }
      ] },
    { id: 'CLR-2026-0014', inquiryId: 2, agency: AGENCY, topic: 'Source verification', priority: 'Urgent', status: 'open',
      createdAt: '21 Sep 2026, 10:12', updatedAt: '21 Sep 2026, 10:12', unreadAgency: false, unreadMcmc: true,
      inquiry: { title: 'Fake vaccine side-effect statistics infographic', category: 'Health', dateAssigned: '28 Aug 2026', dateSubmitted: '24 Aug 2026',
        description: 'An infographic circulating on social media presents fabricated statistics claiming a 40% adverse reaction rate for a nationally administered vaccine, attributing the data to a non-existent MOH study.',
        mcmcNotes: 'High-visibility case — infographic has been shared over 5,000 times, please prioritize.', evidence: ['infographic.png', 'shared_post_screenshot.jpg'] },
      messages: [
        { from: 'agency', author: 'Dr. Aiman Rashid', role: 'MOH Agency Staff', at: '21 Sep 2026, 10:12', files: ['infographic_crop.png'],
          text: 'The infographic attributes its data to an "MOH 2025 Adverse Events Study". Could MCMC share the original channel link and the first-seen timestamp from the submission? We need it to trace the earliest version before issuing a verdict.' }
      ] },
    { id: 'CLR-2026-0011', inquiryId: 7, agency: AGENCY, topic: 'Missing or unclear evidence', priority: 'Normal', status: 'answered',
      createdAt: '18 Sep 2026, 14:05', updatedAt: '19 Sep 2026, 09:40', unreadAgency: true, unreadMcmc: false,
      inquiry: { title: 'Mental health subsidy clinic rumor', category: 'Health', dateAssigned: '1 Sep 2026', dateSubmitted: '29 Aug 2026',
        description: 'A post claims a new government mental health subsidy allows free unlimited therapy sessions at any private clinic nationwide starting immediately, without any official announcement.',
        mcmcNotes: 'Please confirm if the subsidy program mentioned actually exists.', evidence: ['post_screenshot.jpg'] },
      messages: [
        { from: 'agency', author: 'Dr. Aiman Rashid', role: 'MOH Agency Staff', at: '18 Sep 2026, 14:05', files: [],
          text: 'The screenshot is cropped and the page name is not visible. Does the submitter\u2019s original upload include the account name or the post URL?' },
        { from: 'mcmc', author: 'Siti Rahman', role: 'MCMC Staff', at: '19 Sep 2026, 09:40', files: ['post_full_uncropped.png'],
          text: 'Yes — the submitter provided the full URL in a follow-up: socialpost.example/p/50213, posted by the page "Info Kesihatan Rakyat" (about 12k followers). The uncropped screenshot is attached.' }
      ] },
    { id: 'CLR-2026-0007', inquiryId: 3, agency: AGENCY, topic: 'Related or duplicate cases', priority: 'Normal', status: 'closed',
      createdAt: '19 Aug 2026, 11:20', updatedAt: '20 Aug 2026, 09:05', unreadAgency: false, unreadMcmc: false, closedBy: 'Dr. Aiman Rashid',
      inquiry: { title: 'Misleading clinic pricing claim', category: 'Health', dateAssigned: '20 Aug 2026', dateSubmitted: '18 Aug 2026',
        description: 'A public post alleges that a named private clinic is charging patients triple the government-set rate for a basic health screening package.',
        mcmcNotes: 'Please verify against consumer affairs pricing complaint records.', evidence: ['receipt_photo.jpg'] },
      messages: [
        { from: 'agency', author: 'Dr. Aiman Rashid', role: 'MOH Agency Staff', at: '19 Aug 2026, 11:20', files: [],
          text: 'Has MCMC received other complaints about the same clinic in the last 6 months? It would help us decide whether this is an isolated case.' },
        { from: 'mcmc', author: 'Ahmad Faizal', role: 'MCMC Staff', at: '19 Aug 2026, 16:48', files: ['related_cases_extract.pdf'],
          text: 'Two related submissions were received (INQ-0841 and INQ-0907), both about the same clinic branch. Extract attached.' },
        { from: 'system', author: 'Dr. Aiman Rashid', role: 'MOH Agency Staff', at: '20 Aug 2026, 09:05', files: [],
          text: 'Marked as resolved — clarification received, proceeding with investigation.' }
      ] }
  ];

  var memory = null;
  function clone(x) { return JSON.parse(JSON.stringify(x)); }
  function norm(list) { list.forEach(function (t) { if (!t.consults) t.consults = []; }); return list; }
  function read() {
    if (memory) return memory;
    try { var raw = localStorage.getItem(KEY); if (raw) { memory = norm(JSON.parse(raw)); return memory; } } catch (e) {}
    memory = norm(clone(SEED)); return memory;
  }
  function short(n) { var m = String(n).match(/\(([^)]+)\)/); return m ? m[1] : n; }
  function write(list) {
    memory = list;
    try { localStorage.setItem(KEY, JSON.stringify(list)); } catch (e) {}
    try { window.dispatchEvent(new CustomEvent(EV)); } catch (e) {}
  }
  function pad(n) { return n < 10 ? '0' + n : '' + n; }
  function stamp() {
    var d = new Date(), M = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return d.getDate() + ' ' + M[d.getMonth()] + ' ' + d.getFullYear() + ', ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
  }
  function parse(s) { var t = Date.parse(String(s).replace(',', '')); return isNaN(t) ? 0 : t; }
  function nextId(list) {
    var max = list.reduce(function (a, t) { var n = parseInt(String(t.id).split('-').pop(), 10); return n > a ? n : a; }, 0);
    var n = String(max + 1); while (n.length < 4) n = '0' + n;
    return 'CLR-' + new Date().getFullYear() + '-' + n;
  }
  function mutate(id, fn) {
    var list = clone(read()), t = list.filter(function (x) { return x.id === id; })[0];
    if (!t) return null;
    fn(t); t.updatedAt = t.messages.length ? t.messages[t.messages.length - 1].at : t.updatedAt;
    write(list); return t;
  }

  var api = {
    TOPICS: TOPICS,
    STATUS_LABEL: { open: 'Awaiting MCMC Response', answered: 'MCMC Responded', closed: 'Resolved' },
    stamp: stamp,
    all: function () { return clone(read()).sort(function (a, b) { return parse(b.updatedAt) - parse(a.updatedAt); }); },
    get: function (id) { var t = read().filter(function (x) { return x.id === id; })[0]; return t ? clone(t) : null; },
    forInquiry: function (inqId) { return api.all().filter(function (t) { return t.inquiryId === inqId; }); },
    active: function (inqId) { return api.forInquiry(inqId).filter(function (t) { return t.status !== 'closed'; })[0] || null; },
    create: function (o) {
      var list = clone(read());
      if (list.some(function (t) { return t.inquiryId === o.inquiryId && t.status !== 'closed'; })) throw new Error('An active clarification request already exists for this inquiry.');
      var at = stamp();
      var t = { id: nextId(list), inquiryId: o.inquiryId, agency: o.agency || AGENCY, topic: o.topic, priority: o.priority || 'Normal', status: 'open',
        createdAt: at, updatedAt: at, unreadAgency: false, unreadMcmc: true, inquiry: o.inquiry,
        messages: [{ from: 'agency', author: o.author, role: o.role, at: at, files: o.files || [], text: o.text }], consults: [] };
      list.push(t); write(list); return clone(t);
    },
    reply: function (id, o) {
      return mutate(id, function (t) {
        if (t.status === 'closed') throw new Error('This clarification request is already closed.');
        t.messages.push({ from: o.from, author: o.author, role: o.role, at: stamp(), files: o.files || [], text: o.text });
        if (o.from === 'mcmc') { t.status = 'answered'; t.unreadAgency = true; t.unreadMcmc = false; }
        else { t.status = 'open'; t.unreadMcmc = true; t.unreadAgency = false; }
      });
    },
    close: function (id, o) {
      return mutate(id, function (t) {
        if (t.status === 'closed') return;
        t.messages.push({ from: 'system', author: o.author, role: o.role, at: stamp(), files: [], text: o.note });
        t.consults.forEach(function (c) { if (c.status !== 'ended') { c.status = 'ended'; c.unread = false; } });
        t.status = 'closed'; t.closedBy = o.author; t.unreadAgency = o.side === 'mcmc'; t.unreadMcmc = o.side === 'agency' && !!o.notifyMcmc;
      });
    },
    /* ---- MCMC-mediated consultation ---- */
    invite: function (id, o) {
      return mutate(id, function (t) {
        if (t.status === 'closed') throw new Error('Cannot consult on a closed request.');
        if (o.agency === t.agency) throw new Error('This agency already owns the case.');
        var ex = t.consults.filter(function (c) { return c.agency === o.agency && c.status !== 'ended'; })[0];
        if (ex) throw new Error(short(o.agency) + ' is already consulting on this request.');
        var at = stamp();
        t.consults.push({ agency: o.agency, question: o.question, invitedBy: o.author, invitedAt: at, status: 'pending', unread: true });
        t.messages.push({ from: 'system', kind: 'invite', agency: o.agency, author: o.author, role: o.role, at: at, files: [],
          text: o.author + ' (MCMC) invited ' + o.agency + ' to consult on this request.' });
      });
    },
    consultReply: function (id, o) {
      return mutate(id, function (t) {
        var c = t.consults.filter(function (x) { return x.agency === o.agency && x.status !== 'ended'; })[0];
        if (!c || t.status === 'closed') throw new Error('This consultation has ended.');
        t.messages.push({ from: 'consult', agency: o.agency, author: o.author, role: o.role, at: stamp(), files: o.files || [], text: o.text });
        c.status = 'responded'; c.unread = false; c.respondedAt = stamp();
        t.unreadMcmc = true;
      });
    },
    endConsult: function (id, o) {
      return mutate(id, function (t) {
        var c = t.consults.filter(function (x) { return x.agency === o.agency && x.status !== 'ended'; })[0];
        if (!c) return;
        c.status = 'ended'; c.unread = false;
        t.messages.push({ from: 'system', kind: 'endConsult', agency: o.agency, author: o.author, role: o.role, at: stamp(), files: [],
          text: 'Consultation with ' + o.agency + ' ended by ' + o.author + ' (MCMC).' });
      });
    },
    consultsFor: function (agency) {
      return api.all().filter(function (t) { return (t.consults || []).some(function (c) { return c.agency === agency; }); })
        .map(function (t) { t.myConsult = (t.consults || []).filter(function (c) { return c.agency === agency; }).slice(-1)[0]; return t; });
    },
    markConsultRead: function (id, agency) {
      var t = read().filter(function (x) { return x.id === id; })[0];
      if (!t || !(t.consults || []).some(function (c) { return c.agency === agency && c.unread; })) return;
      var list = clone(read()), tt = list.filter(function (x) { return x.id === id; })[0];
      tt.consults.forEach(function (c) { if (c.agency === agency) c.unread = false; });
      write(list);
    },
    shortAgency: short,
    markRead: function (id, side) {
      var t = read().filter(function (x) { return x.id === id; })[0];
      if (!t) return;
      if ((side === 'agency' && t.unreadAgency) || (side === 'mcmc' && t.unreadMcmc)) mutate(id, function (x) { if (side === 'agency') x.unreadAgency = false; else x.unreadMcmc = false; });
    },
    counts: function () {
      var l = read();
      return {
        open: l.filter(function (t) { return t.status === 'open'; }).length,
        answered: l.filter(function (t) { return t.status === 'answered'; }).length,
        unreadMcmc: l.filter(function (t) { return t.unreadMcmc; }).length,
        unreadAgency: l.filter(function (t) { return t.unreadAgency; }).length
      };
    },
    subscribe: function (fn) {
      var onStorage = function (e) { if (!e || e.key === KEY || e.key === null) { memory = null; fn(); } };
      window.addEventListener(EV, fn); window.addEventListener('storage', onStorage);
      return function () { window.removeEventListener(EV, fn); window.removeEventListener('storage', onStorage); };
    },
    reset: function () { write(clone(SEED)); }
  };
  window.SBClarify = api;
})();
