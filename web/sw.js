/* גאיהלנד — Service Worker (network-first)
 * עיקרון: תמיד מנסים רשת קודם, כדי שאף פעם לא רואים גרסה ישנה.
 * המטמון משמש רק כגיבוי כשאין אינטרנט.
 */
const CACHE = 'gayaland-v1';

self.addEventListener('install', function (e) {
  self.skipWaiting();   // גרסה חדשה נכנסת לתוקף מיד
});

self.addEventListener('activate', function (e) {
  e.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.filter(function (k) { return k !== CACHE; })
        .map(function (k) { return caches.delete(k); }));
    }).then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('message', function (e) {
  if (e.data && e.data.type === 'SKIP_WAITING') self.skipWaiting();
});

self.addEventListener('fetch', function (e) {
  var req = e.request;
  if (req.method !== 'GET') return;                         // רק קריאות GET

  var url = new URL(req.url);
  // לא נוגעים בקריאות API / גוגל / נייקס — הן חייבות תמיד רשת חיה
  if (url.origin !== location.origin) return;

  // network-first: מנסים רשת, נופלים למטמון רק אם אין חיבור
  e.respondWith(
    fetch(req).then(function (res) {
      if (res && res.status === 200) {
        var copy = res.clone();
        caches.open(CACHE).then(function (c) { c.put(req, copy); });
      }
      return res;
    }).catch(function () {
      return caches.match(req).then(function (cached) {
        return cached || caches.match('./');
      });
    })
  );
});
