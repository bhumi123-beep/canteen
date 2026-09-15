<?php
namespace PHPMailer\PHPMailer;

class PHPMailer
{
    public const ENCRYPTION_STARTTLS = 'tls';

    public $Host = '';
    public $SMTPAuth = false;
    public $Username = '';
    public $Password = '';
    public $SMTPSecure = self::ENCRYPTION_STARTTLS;
    public $Port = 587;
    public $From = [];
    public $to = [];
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $ErrorInfo = '';
    public $exceptions = false;
    private $smtpMode = false;

    public function __construct($exceptions = false)
    {
        $this->exceptions = (bool)$exceptions;
    }

    public function isSMTP()
    {
        $this->smtpMode = true;
        return true;
    }

    public function setFrom($address, $name = '')
    {
        $this->From = [$address, $name];
        return true;
    }

    public function addAddress($address, $name = '')
    {
        $this->to[] = [$address, $name];
        return true;
    }

    public function send()
    {
        if (!$this->smtpMode) {
            $this->ErrorInfo = 'SMTP is not enabled for this message.';
            return false;
        }

        if (!$this->Host || !$this->Username || !$this->Password) {
            $this->ErrorInfo = 'SMTP host or credentials are missing.';
            return false;
        }

        $smtp = new SMTP();
        if (!$smtp->connect($this->Host, $this->Port, 30)) {
            $this->ErrorInfo = $smtp->ErrorInfo;
            return false;
        }

        if (!$smtp->hello('localhost')) {
            $this->ErrorInfo = 'EHLO failed.';
            $smtp->quit();
            return false;
        }

        if ($this->SMTPSecure === self::ENCRYPTION_STARTTLS && !$smtp->startTLS()) {
            $this->ErrorInfo = $smtp->ErrorInfo;
            $smtp->quit();
            return false;
        }

        if ($this->SMTPAuth && !$smtp->authenticate($this->Username, $this->Password)) {
            $this->ErrorInfo = $smtp->ErrorInfo;
            $smtp->quit();
            return false;
        }

        $fromEmail = $this->From[0] ?? '';
        if (!$fromEmail || !$smtp->mailFrom($fromEmail)) {
            $this->ErrorInfo = 'MAIL FROM failed.';
            $smtp->quit();
            return false;
        }

        foreach ($this->to as $recipient) {
            $toEmail = $recipient[0] ?? '';
            if ($toEmail && !$smtp->rcptTo($toEmail)) {
                $this->ErrorInfo = 'RCPT TO failed for ' . $toEmail;
                $smtp->quit();
                return false;
            }
        }

        $toList = [];
        foreach ($this->to as $recipient) {
            $toList[] = ($recipient[1] ?? '') ? ($recipient[1] . ' <' . $recipient[0] . '>') : $recipient[0];
        }

        $headers = [
            'From: ' . ($this->From[1] ? ($this->From[1] . ' <' . $this->From[0] . '>') : $this->From[0]),
            'To: ' . implode(', ', $toList),
            'Subject: ' . $this->Subject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];

        $body = $this->Body ?: $this->AltBody;
        $rawMessage = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body), 76, "\r\n");

        if (!$smtp->data($rawMessage)) {
            $this->ErrorInfo = $smtp->ErrorInfo;
            $smtp->quit();
            return false;
        }

        $smtp->quit();
        return true;
    }
}
