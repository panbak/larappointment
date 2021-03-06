<?php

namespace App\Http\Controllers;

use App\Appointment;
use App\User;
use App\DailyAppointment;
use App\Timeslot;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Option;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;


class PageController extends Controller
{
    public function index(){
        $options = Option::setEagerLoads([])->where('parent',null)->with('appointments')->get();
        //Log::info($options);
        foreach ($options as $key=>$option) {
            $option->hasAppointment = 0;
            foreach ($option->appointments as $appointment) {
                if ($appointment->enabled) {
                    $option->hasAppointment = 1;
                    break;
                }
            }
        }

        $level1 = Option::where('parent', -1)->first();
        return view('pages.index')->with(['options' => $options, 'level1' => $level1]);
    }

    public function datePicker(){
        $optionId = Input::get('option');

        session(['optionId' => $optionId]);

        $dailyAppointments = DailyAppointment::whereHas('appointment.option', function ($q) use($optionId) {
            $q->where('id', $optionId);
        })->where('free_slots', '>', 0)->orderBy('date', 'desc')->get();

        if($dailyAppointments->isEmpty()){
            return view('pages.notAvailableAppointments');
        }

        $isTicket = false;
        $appointment  = Appointment::where('belong_to_option', $optionId)->first();
        if($appointment->type=='ticket'){
            $isTicket = true;
        }

        $current = date("Y-m-d");
        $disabledDates = [$current];


        while($current <= $dailyAppointments[0]->date){
            $current = date('Y-m-d', strtotime($current .' +1 day'));
            array_push($disabledDates, $current);
        }


        foreach ($dailyAppointments as $dailyAppointment){
            if (($key = array_search(substr($dailyAppointment->date, 0, -9), $disabledDates)) !== false) {
                unset($disabledDates[$key]);
            }
        }

        $maxAvailDate = $dailyAppointments[0]->date;

        if($isTicket){
            return view('pages.datepickerTicket')->with(['disabledDates'=>$disabledDates, 'maxAvailDate'=>$maxAvailDate]);
        }

        return view('pages.datepicker')->with(['disabledDates'=>$disabledDates, 'maxAvailDate'=>$maxAvailDate]);
    }

    public function adminDashboard(){

        if (Auth::check() && Auth::user()->role=='admin' ) {
            $timeslotsToday = Timeslot::where('slot', 'like', Carbon::now()->format('Y-M-d').'%')->where('user_id', '!=', null)->count();
            $timeslotsMonth = Timeslot::where('slot', 'like', '%-'.date('m').'-%')->where('user_id', '!=', null)->where('slot', '>', Carbon::now()->toDateTimeString())->count();
            $usersNum = User::where('role', '!=', 'admin')->count();
            //Log::info(date('m'));
            $timeslots = Timeslot::with('user')->with('daily_appointment.appointment.option')->where('slot', '>', Carbon::now()->toDateTimeString())->where('user_id', '!=', null)->get();

            foreach ($timeslots as $timeslot) {
                $timeslot->daily_appointment->date = substr($timeslot->daily_appointment->date, 0, 10);
                $timeslot->slot = substr($timeslot->slot, 11, 5);

                $parent = Option::setEagerLoads([])->whereHas('children', function ($q) use ($timeslot) {
                    $q->where('id', $timeslot->daily_appointment->appointment->option->id);
                })->first();

                $parents = [$timeslot->daily_appointment->appointment->option->title];
                if ($parent) {
                    array_push($parents, $parent->title);
                    while ($parent) {
                        $id = $parent->id;
                        $parent = Option::setEagerLoads([])->whereHas('children', function ($q) use ($id) {
                            $q->where('id', $id);
                        })->first();
                        if ($parent) {
                            array_push($parents, $parent->title);
                        }
                    }
                }

                $timeslot->parents=array_reverse($parents);
            }



            return view('pages.admin.adminDashboard')->with(['timeslots' => $timeslots, 'timeslotsToday' => $timeslotsToday, 'timeslotsMonth'=>$timeslotsMonth, 'usersNum' => $usersNum]);
        } else {
            abort(403, 'Unauthorized action.');
        }
    }

    public function hierarchy(){

        if (Auth::check() && Auth::user()->role=='admin') {
            return view('pages.admin.hierarchy');
        } else {
            abort(403, 'Unauthorized action.');
        }
    }

    public function createAppointment(){

        if (Auth::check() && Auth::user()->role=='admin') {

            $options = Option::doesntHave('children')->with('appointments')->get();
            $options = $options->keyBy('id');
            /*foreach($options as $option){
                $parent = $option->getParent;
                if($parent) {
                    while ($parent->id != -1) { // this loop removes the last level child from available appointment types
                        $parent = $parent->getParent;
                        if ($parent) {
                            if ($parent->parent == -1) {
                                //Log::info($option->title);
                                $options->forget($option->id);
                            }
                        } else {
                            break;
                        }
                    }
                }
            }*/
            foreach ($options as $option){
                if(!$option->appointments->isEmpty()){
                    $options->forget($option->id);
                }
            }

            $level = Option::where('parent', -1)->first();
            if($level){
                $previous = $level;
                while($level){
                    $level = Option::where('parent', $level->id)->first();
                    if($level){
                        $previous = $level;
                    }
                }
                $options->forget($previous->id);
            }
            return view('pages.admin.createAppointment')->with('options', $options);
        } else {
            abort(403, 'Unauthorized action.');
        }
    }

    public function manageAppointments(){

        if (Auth::check() && Auth::user()->role=='admin') {
            $appointments = Appointment::with('daily_appointments','daily_appointments.timeslots')->get();

            foreach ($appointments as $appointment){

                foreach ($appointment->daily_appointments as $daily_appointment){
                    $appointment->OccupiedSlots+= $daily_appointment->timeslots->where('user_id','!=',null)->count();
                }
                }

            /*$parent = Option::setEagerLoads([])->whereHas('children',function($q) use($timeslot) {
                $q->where('id',$timeslot->daily_appointment->appointment->option->id);
            })->first();

            $parents=[$timeslot->daily_appointment->appointment->option->title];
            if($parent){
                array_push($parents, $parent->title);
                while($parent){
                    $id = $parent->id;
                    $parent = Option::setEagerLoads([])->whereHas('children',function($q) use($id) {
                        $q->where('id',$id);
                    })->first();
                    if($parent){
                        array_push($parents, $parent->title);
                    }
                }
            }

            $parents=array_reverse($parents);*/

            return view('pages.admin.manageAppointments')->with('appointments', $appointments);
        } else {
            abort(403, 'Unauthorized action.');
        }
    }

    public function levels(){
        if (Auth::check() && Auth::user()->role=='admin') {
            $options = Option::all();
            $levels = array();
            $depth = 1;
            $done = false;
            while(!$done){
                $levels[$depth] = array();
                foreach($options as $option){
                    if($depth == 1){
                        if($option->parent == NULL){
                            array_push($levels[$depth],$option);
                        }
                    }else{
                        if(count(array_filter($levels[$depth-1],function($o) use ($option){
                                return $o->id == $option->parent;
                            })) > 0){
                            array_push($levels[$depth],$option);
                        }
                    }
                }
                if(count($levels[$depth]) == 0 ){
                    $done = true;
                }
                $depth++;
            }

            $levelNames = array();
            $level = Option::setEagerLoads([])->where('parent', -1)->first();
            if($level){
                array_push($levelNames, $level->title);
                while($level){
                    $id = $level->id;
                    $level = Option::where('parent', $id)->first();
                    if($level){
                        array_push($levelNames, $level->title);
                    }
                }
            }


            return view('pages.admin.levels')->with(['levels' => $levels, 'levelNames' => $levelNames]);
        } else {
            abort(403, 'Unauthorized action.');
        }
    }

    public function appointmentHistory(){
        if (Auth::check() && Auth::user()->role=='admin' ) {

            $timeslots = Timeslot::with('user')->with('daily_appointment.appointment.option')->where('slot', '<', Carbon::now()->toDateTimeString())->where('user_id', '!=', null)->get();

            foreach ($timeslots as $timeslot) {
                $timeslot->daily_appointment->date = substr($timeslot->daily_appointment->date, 0, 10);
                $timeslot->slot = substr($timeslot->slot, 11, 5);

                $parent = Option::setEagerLoads([])->whereHas('children', function ($q) use ($timeslot) {
                    $q->where('id', $timeslot->daily_appointment->appointment->option->id);
                })->first();

                $parents = [$timeslot->daily_appointment->appointment->option->title];
                if ($parent) {
                    array_push($parents, $parent->title);
                    while ($parent) {
                        $id = $parent->id;
                        $parent = Option::setEagerLoads([])->whereHas('children', function ($q) use ($id) {
                            $q->where('id', $id);
                        })->first();
                        if ($parent) {
                            array_push($parents, $parent->title);
                        }
                    }
                }

                $timeslot->parents=array_reverse($parents);
            }



            return view('pages.admin.appointmentHistory')->with('timeslots',$timeslots);
        } else {
            abort(403, 'Unauthorized action.');
        }
    }

    public function getTicket()
    {
        if(Session::has('Stimeslot')) {
            $timeslot=Session::get('Stimeslot');
            //Log::info($timeslot);
            $parents=Session::get('Sparents');
            //Log::info($parents);
            $startingHour=Session::get('SstartingHour');
            return view('pages.Ticket')->with(['timeslot' => $timeslot,'parents'=>$parents, 'startingHour'=>$startingHour]);
        }else{
            return redirect('/');

        }

    }

    public function TicketsId(){
        if (Auth::check() && Auth::user()->role=='admin' ) {

            $dailyAppoinments = DailyAppointment::with('appointment.option')->where('date', '>', Carbon::now())->where('free_slots', '>', 0)->get();


            return view('pages.admin.TicketsId')->with('dailyAppoinments',$dailyAppoinments);
        } else {
            abort(403, 'Unauthorized action.');
        }
    }
}
