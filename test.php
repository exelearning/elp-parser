<?php

require "vendor/autoload.php";

use Erseco\Message;


$messageString = <<<EOF
from: Sender <no-reply@example.com>
to: Receiver <receiver@example.com>
subject: Test Subject
message-id: <6e30b164904cf01158c7cc58f144b9ca@example.com>
mime-version: 1.0
date: Fri, 25 Aug 2023 15:36:13 +0200
content-type: text/html; charset=utf-8
content-transfer-encoding: quoted-printable

Email content goes here.
EOF;

    $message = Message::fromString($messageString);

print_r($message->getHeaders());
die();

$messageString = <<<EOF
From: Sender <no-reply@example.com>
To: Receiver <receiver@example.com>
Subject: Test Subject
Message-ID: <6e30b164904cf01158c7cc58f144b9ca@example.com>
MIME-Version: 1.0
Date: Fri, 25 Aug 2023 15:36:13 +0200
Content-Type: text/html; charset=utf-8
Content-Transfer-Encoding: quoted-printable

Email content goes here.
EOF;


    $message = Message::fromString($messageString);



print_r($message->getHtmlPart());


    // expect($message->getFrom())->toBe('Sender <no-reply@example.com>')
    //     ->and($message->getTo())->toBe('Receiver <receiver@example.com>')
    //     ->and($message->getSubject())->toBe('Test Subject')
    //     ->and($message->getId())->toBe('6e30b164904cf01158c7cc58f144b9ca@example.com')
    //     ->and($message->getDate()?->format('Y-m-d H:i:s'))->toBe('2023-08-25 15:36:13')
    //     ->and($message->getContentType())->toBe('text/html; charset=utf-8')
    //     ->and($message->getHtmlPart()?->getContent())->toBe('Email content goes here.')
    //     ->and($message->getHtmlPart()?->getHeaders())->toBe([
    //         'Content-Type' => 'text/html; charset=utf-8',
    //         'Content-Transfer-Encoding' => 'quoted-printable',
    //     ]);


die();



// Parse a message from a string
$rawEmail = file_get_contents(__DIR__ . '/tests/Fixtures/multi_attachment_email.eml');
$parser = new Message($rawEmail);

$parser->getHeaders();                 // get all headers
$parser->getContentType();             // 'multipart/mixed; boundary="----=_Part_1_1234567890"'
$parser->getFrom();                    // 'Service <service@example.com>'
$parser->getTo();                      // 'John Doe <johndoe@example.com>'
$parser->getSubject();                 // 'Subject line'
$parser->getDate();                    // DateTime object when the email was sent

$parser->getParts();       // Returns an array of parts, which can be html parts, text parts, attachments, etc.
$parser->getHtmlPart();    // Returns the HTML content
$parser->getTextPart();    // Returns the Text content
$parser->getAttachments(); // Returns an array of attachments

$parts = $parser->getParts();
$firstPart = $parts[0];

$firstPart->headers;                 // array of all headers for this message part
$firstPart->contentType;             // 'text/html; charset="utf-8"'
$firstPart->content;                 // '<html><body>....'
$firstPart->isHtml;                  // true if it's an HTML part
$firstPart->isAttachment;            // true if it's an attachment
// $firstPart->filename;                // name of the file, in case this is an attachment part

// echo "\n----- PARTS ----\n";
// print_r($parts);

echo "\n----- HTML PART ----\n";
print_r($parser->getHtmlPart());

echo "\n----- TEXT PART ----\n";
print_r($parser->getTextPart());

echo "\n----- HEADERS ----\n";
print_r($parser->getHeaders());

echo "\n----- ATTACHMENTS ----\n";
// print_r($parser->getAttachments());

foreach ($parts as $part) {
    if ($part->isAttachment) {
        echo $part->filename . "\n";
    }
}
