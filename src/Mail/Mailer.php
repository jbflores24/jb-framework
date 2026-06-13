<?php

declare(strict_types=1);

namespace Jb\Mail;

class Mailer
{
    public function __construct(
        private readonly string $fromAddress,
        private readonly string $fromName = 'JB API'
    ) {
    }

    /**
     * Send a plain text email using PHP mail().
     */
    public function send(string $to, string $subject, string $body): bool
    {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $this->fromName . ' <' . $this->fromAddress . '>',
        ];

        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
}
