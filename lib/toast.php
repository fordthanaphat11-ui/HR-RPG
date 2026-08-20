<?php
declare(strict_types=1);

function appToast(string $type, string $message, ?int $duration = null): void
{
    $type = in_array($type, ['success','error','warning','info'], true) ? $type : 'info';
    $message = trim($message);
    if ($message === '') return;
    $GLOBALS['app_toasts'] ??= [];
    $GLOBALS['app_toasts'][] = ['type'=>$type, 'message'=>$message, 'duration'=>$duration];
}

function appFlashToast(string $type, string $message, ?int $duration = null): void
{
    $_SESSION['app_toasts'] ??= [];
    $_SESSION['app_toasts'][] = ['type'=>$type, 'message'=>trim($message), 'duration'=>$duration];
}

function appConsumeToasts(): array
{
    $toasts = array_merge((array) ($_SESSION['app_toasts'] ?? []), (array) ($GLOBALS['app_toasts'] ?? []));
    unset($_SESSION['app_toasts']);
    $GLOBALS['app_toasts'] = [];
    return array_values(array_filter($toasts, static fn(array $toast): bool => trim((string)($toast['message'] ?? '')) !== ''));
}

function appTriggerToast(string $type, string $message, ?int $duration = null): void
{
    $payload = ['showToast'=>['type'=>$type, 'message'=>$message]];
    if ($duration !== null) $payload['showToast']['duration'] = $duration;
    // HTTP headers are byte-oriented. Keep non-ASCII text escaped so every web server
    // and proxy can forward the HTMX trigger without corrupting Thai messages.
    header('HX-Trigger: ' . json_encode($payload, JSON_UNESCAPED_SLASHES));
}

function appRequestToast(string $type, string $message, ?int $duration = null): void
{
    if (isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true') appTriggerToast($type, $message, $duration);
    else appToast($type, $message, $duration);
}
