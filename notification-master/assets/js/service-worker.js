// Handle push events
self.addEventListener('push', function (event) {
    // Type assertion for `event.data`
    var data = event.data ? event.data.json() : {};

    var options = {
        body: data.message,
        icon: data.icon || '',
        image: data.image || '',
        data: {
            url: data.url || '', // Default URL if none provided
        },
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// Handle notification click events
self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    // Accessing `data` from the notification
    var targetUrl = (event.notification.data && event.notification.data.url) || '';

    event.waitUntil(
        self.clients.openWindow(targetUrl)
    );
});
