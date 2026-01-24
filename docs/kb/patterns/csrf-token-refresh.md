# CSRF Token Refresh Pattern

> **Probleem:** Laravel CSRF tokens verlopen na ~2 uur. Gebruikers krijgen 419 error na inactiviteit.

## Oplossing: Automatische CSRF Refresh

### Optie 1: Fetch wrapper (aanbevolen voor Blade views)

```javascript
async function fetchWithCsrf(url, options = {}) {
    options.headers = {
        ...options.headers,
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json',
    };

    let response = await fetch(url, options);

    if (response.status === 419) {
        // Token verlopen - ververs en probeer opnieuw
        const refreshResponse = await fetch('/csrf-refresh');
        const data = await refreshResponse.json();
        document.querySelector('meta[name="csrf-token"]').content = data.token;

        options.headers['X-CSRF-TOKEN'] = data.token;
        response = await fetch(url, options);
    }

    return response;
}
```

### Optie 2: Axios interceptor (voor JS apps met axios)

```javascript
// In bootstrap.js of app.js
axios.interceptors.response.use(
    response => response,
    async error => {
        if (error.response?.status === 419) {
            const { data } = await axios.get('/csrf-refresh');
            document.querySelector('meta[name="csrf-token"]').content = data.token;
            axios.defaults.headers.common['X-CSRF-TOKEN'] = data.token;

            // Retry original request
            error.config.headers['X-CSRF-TOKEN'] = data.token;
            return axios.request(error.config);
        }
        return Promise.reject(error);
    }
);
```

## Laravel Route (verplicht in elk project)

```php
// routes/web.php
Route::get('/csrf-refresh', function () {
    return response()->json(['token' => csrf_token()]);
})->name('csrf.refresh');
```

## Projecten met deze fix

| Project | Status | Datum |
|---------|--------|-------|
| HavunAdmin | ✅ Route + helper + fetch calls updated | 24-01-2026 |
| Herdenkingsportaal | ✅ Route + helper toegevoegd | 24-01-2026 |
| SafeHavun | ✅ Route + helper toegevoegd | 24-01-2026 |
| infosyst | ✅ Route + helper (frontend + admin) | 24-01-2026 |

## Checklist nieuwe AJAX calls

- [ ] Gebruik `fetchWithCsrf()` of axios met interceptor
- [ ] `/csrf-refresh` route bestaat
- [ ] Meta tag `<meta name="csrf-token" content="{{ csrf_token() }}">` aanwezig
