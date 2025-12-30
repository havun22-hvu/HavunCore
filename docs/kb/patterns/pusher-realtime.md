# Pattern: Pusher Real-time WebSockets

> Herbruikbaar pattern voor real-time communicatie in Havun projecten.

## Wanneer gebruiken

- Real-time updates tussen users (chat, notificaties, live sync)
- Mentor-leerling synchronisatie
- Live dashboards
- Vermijd polling voor 24/7 apps

## Account

- **Provider:** Pusher Channels
- **Login:** GitHub havun22-hvu
- **Dashboard:** https://dashboard.pusher.com
- **Credentials:** Zie `.claude/context.md`

## Backend Setup (Laravel)

### 1. Package installeren

```bash
composer require pusher/pusher-php-server
```

### 2. .env configureren

```env
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_key
PUSHER_APP_SECRET=your_secret
PUSHER_APP_CLUSTER=eu
```

### 3. config/broadcasting.php

```php
'pusher' => [
    'driver' => 'pusher',
    'key' => env('PUSHER_APP_KEY'),
    'secret' => env('PUSHER_APP_SECRET'),
    'app_id' => env('PUSHER_APP_ID'),
    'options' => [
        'cluster' => env('PUSHER_APP_CLUSTER', 'eu'),
        'useTLS' => true,
    ],
],
```

### 4. Event class maken

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class SessionStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $sessionId;
    public $studentName;

    public function __construct($sessionId, $studentName)
    {
        $this->sessionId = $sessionId;
        $this->studentName = $studentName;
    }

    public function broadcastOn()
    {
        return new Channel('mentor.' . $this->mentorId);
    }

    public function broadcastAs()
    {
        return 'session.started';
    }
}
```

### 5. Event dispatchen

```php
// In controller of service
event(new SessionStarted($session->id, $student->name));
```

## Frontend Setup (React)

### 1. Packages installeren

```bash
npm install pusher-js laravel-echo
```

### 2. Echo configureren

```javascript
// src/services/echo.js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true
});

export default echo;
```

### 3. .env frontend

```env
VITE_PUSHER_APP_KEY=your_public_key
VITE_PUSHER_APP_CLUSTER=eu
```

### 4. Luisteren naar events

```javascript
import echo from './services/echo';

// In component
useEffect(() => {
    const channel = echo.channel(`mentor.${mentorId}`);

    channel.listen('.session.started', (data) => {
        console.log('Sessie gestart:', data);
        // Update state, show notification, etc.
    });

    return () => {
        echo.leave(`mentor.${mentorId}`);
    };
}, [mentorId]);
```

## Private Channels (authenticated)

Voor user-specifieke data, gebruik private channels:

### Backend

```php
// routes/channels.php
Broadcast::channel('mentor.{mentorId}', function ($user, $mentorId) {
    return $user->id === (int) $mentorId;
});
```

### Frontend

```javascript
echo.private(`mentor.${mentorId}`)
    .listen('.session.started', (data) => {
        // ...
    });
```

## Presence Channels (online status)

Voor "wie is online" functionaliteit:

```javascript
echo.join(`session.${sessionId}`)
    .here((users) => {
        console.log('Online users:', users);
    })
    .joining((user) => {
        console.log('User joined:', user);
    })
    .leaving((user) => {
        console.log('User left:', user);
    });
```

## Best Practices

1. **Channel naming:** `{entity}.{id}` bijv. `mentor.123`, `session.456`
2. **Event naming:** `{entity}.{action}` bijv. `session.started`, `message.sent`
3. **Geen secrets in frontend** - alleen public key
4. **Private channels** voor user-specifieke data
5. **Cleanup:** Altijd `echo.leave()` in useEffect cleanup

## Debugging

```javascript
// In development
Pusher.logToConsole = true;
```

## Projecten die dit gebruiken

- Studieplanner (mentor-leerling sync)

---

*Pattern toegevoegd: 2025-12-30*
