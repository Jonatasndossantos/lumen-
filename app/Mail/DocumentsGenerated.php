<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DocumentsGenerated extends Mailable
{
    use Queueable, SerializesModels;

    public $documents;
    public $name;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($documents, $name)
    {
        $this->documents = $documents;
        $this->name = $name;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Seus documentos foram gerados')
                    ->view('emails.documents-generated');
    }
} 