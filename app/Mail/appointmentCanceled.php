<?php

namespace App\Mail;


use App\Option;
use App\Timeslot;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class appointmentCanceled extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Timeslot $timeslot, $parents)
    {
        $this->timeslot=$timeslot;
        $this->parents=$parents;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mails.appointmentCanceled')->text('mails.appointmentCanceledPlain')->subject("Appointment Canceled")->with(['timeslot'=>$this->timeslot, 'parents'=>$this->parents]);
    }
}
