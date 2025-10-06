<?php

class Mailer
{
    private ?string $fromAddress;
    private ?string $fromName;
    /**
     * @var string[]
     */
    private array $adminRecipients;

    public function __construct(array $config = [])
    {
        $this->fromAddress = $this->normalizeAddress($config['from_address'] ?? null);
        $this->fromName = $this->normalizeText($config['from_name'] ?? null);
        $this->adminRecipients = $this->normalizeRecipients($config['admin_recipients'] ?? []);
    }

    public function hasSender(): bool
    {
        return $this->fromAddress !== null;
    }

    public function hasAdminRecipients(): bool
    {
        return !empty($this->adminRecipients);
    }

    /**
     * @param string|string[] $to
     */
    public function send($to, string $subject, string $body): bool
    {
        if (!$this->hasSender()) {
            return false;
        }

        $recipients = $this->normalizeRecipients($to);
        if (empty($recipients)) {
            return false;
        }

        $headers = [];
        $headers[] = 'From: ' . $this->formatAddress($this->fromName, $this->fromAddress);
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';

        $encodedSubject = $this->encodeHeader($subject);

        return @mail(implode(', ', $recipients), $encodedSubject, $body, implode("\r\n", $headers));
    }

    public function notifyAdmins(string $subject, string $body): bool
    {
        if (!$this->hasAdminRecipients()) {
            return false;
        }

        return $this->send($this->adminRecipients, $subject, $body);
    }

    private function normalizeAddress(?string $address): ?string
    {
        if ($address === null) {
            return null;
        }

        $trimmed = trim($address);
        return $trimmed !== '' ? $trimmed : null;
    }

    private function normalizeText($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        return $text !== '' ? $text : null;
    }

    /**
     * @param string|string[] $input
     * @return string[]
     */
    private function normalizeRecipients($input): array
    {
        if (is_string($input)) {
            $parts = preg_split('/[\r\n,;]+/', $input) ?: [];
        } elseif (is_array($input)) {
            $parts = [];
            foreach ($input as $item) {
                if (is_string($item)) {
                    $parts = array_merge($parts, preg_split('/[\r\n,;]+/', $item) ?: []);
                }
            }
        } else {
            $parts = [];
        }

        $clean = [];
        foreach ($parts as $part) {
            $address = $this->normalizeAddress($part);
            if ($address !== null) {
                $clean[$address] = $address;
            }
        }

        return array_values($clean);
    }

    private function formatAddress(?string $name, string $address): string
    {
        if ($name === null) {
            return $address;
        }

        return $this->encodeHeader($name) . " <{$address}>";
    }

    private function encodeHeader(string $value): string
    {
        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}

