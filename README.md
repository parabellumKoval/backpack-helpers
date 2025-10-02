# Backpack Helpers

Этот пакет расширяет [Laravel Backpack 4.1](https://backpackforlaravel.com/) новыми:
- колонками
- полями
- универсальными трейтами
- сервисами

## Установка

```bash
composer require parabellumkoval/backpack-helpers
```

Или при локальной разработке добавить в `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "packages/backpack/backpack-helpers"
    }
]
```

## Подключение

ServiceProvider подключается автоматически через `extra.laravel.providers`.
