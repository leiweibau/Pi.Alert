# CSRF protection

Pi.Alert separates read-only browser requests from state-changing requests.
Read operations use `GET`; every state-changing action uses `POST` and a token
bound to the current PHP session.

## Adding a backend action

1. Add a read-only action to the endpoint's GET allowlist, or add a mutating
   action to its POST allowlist in the `pialert_dispatch_action()` call. Never
   add an action to both lists.
2. Read input through `pialert_request_data()` (or the endpoint's selected
   request array). Do not use `$_REQUEST`.
3. Ensure a GET action has no database, file, session, journal, process, network
   or device side effects.
4. Put all validation and side effects after dispatch. POST dispatch validates
   the CSRF token before the action handler runs.
5. Unknown actions return 400, an action sent with the wrong method returns 405,
   and a missing or invalid token returns 403.

Browser mutations should use `pialertPost(url, data, callback)` from
`front/js/pialert_common.js`. It moves query parameters into the POST body,
adds the CSRF header only for the same origin, suppresses identical concurrent
requests, and adds an operation identifier used to reject replay of critical
operations. Plain jQuery `$.post()` is also protected by the global same-origin
prefilter. Native `fetch()`, `FormData`, and ordinary forms must explicitly send
the `X-CSRF-Token` header or a `_csrf` form field.

Ordinary mutating forms must use Post/Redirect/Get and respond with HTTP 303.
The logout and report actions are reference implementations. Never put a CSRF
token in a URL.

## Authentication and cookies

The CSRF token is rotated after password login and successful remember-me
login. It remains stable during the authenticated session so multiple tabs and
parallel refresh requests continue to work. Logout validates CSRF before it
revokes the remember token or destroys the session.

PHP session cookies use `HttpOnly`, `SameSite=Strict`, the application path and
`Secure` when HTTPS is detected. Remember-me cookies retain `SameSite=Lax` and
continue to use hashed, rotating bearer tokens; they are independent of CSRF.

## Regression checks

Run:

```sh
php tests/php/test_csrf_security.php
php tests/php/test_auth_cookie_security.php
```

The CSRF test also fails if production PHP code reintroduces `$_REQUEST` or if
a central dispatcher loses its method-aware allowlist.

