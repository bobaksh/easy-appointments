<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Appointments extends CI_Controller {
    /**
     * Default callback method of the application. 
     * 
     * This method creates the appointment book wizard. If an appointment hash
     * is provided then it means that the customer followed the appointment 
     * manage link that was send with the book success email.
     * 
     * @param string $appointment_hash The db appointment hash of an existing 
     * record.
     */
    public function index($appointment_hash = '') {
        if (strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') { 
            $this->load->model('Settings_Model');
            $this->load->model('Services_Model');
            $this->load->model('Providers_Model');

            $company_name        = $this->Settings_Model->get_setting('company_name');
            $available_services  = $this->Services_Model->get_available_services();
            $available_providers = $this->Providers_Model->get_available_providers();

            // If an appointment hash is provided then it means that the customer 
            // is trying to edit a registered record.
            if ($appointment_hash !== ''){ 
                // Load the appointments data and set the manage mode of the page.
                $this->load->model('Appointments_Model');
                $this->load->model('Customers_Model');
                
                $manage_mode = TRUE;
                
                $appointment_data = $this->Appointments_Model
                        ->get_batch(array('hash' => $appointment_hash))[0];
                $provider_data = $this->Providers_Model
                        ->get_row($appointment_data['id_users_provider']);
                $customer_data = $this->Customers_Model
                        ->get_row($appointment_data['id_users_customer']);
            } else {
                // The customer is going to book an appointment so there is no 
                // need for the manage functionality to be initialized.
                $manage_mode        = FALSE;
                $appointment_data   = array();
                $provider_data      = array();
                $customer_data      = array();
            }

            // Load the book appointment view.
            $view_data = array (
                'available_services'    => $available_services,
                'available_providers'   => $available_providers,
                'company_name'          => $company_name,
                'manage_mode'           => $manage_mode,
                'appointment_data'      => $appointment_data,
                'provider_data'         => $provider_data,
                'customer_data'         => $customer_data
            );
            $this->load->view('appointments/book', $view_data);
        } else { 
            // The page is a post-back. Register the appointment and send
            // notification emails to the provider and the customer that are
            // related to the appointment.
            $post_data          = json_decode($_POST['post_data'], true);
            $appointment_data   = $post_data['appointment'];
            $customer_data      = $post_data['customer'];

            $this->load->model('Customers_Model');
            $this->load->model('Appointments_Model');
            $this->load->model('Services_Model');
            $this->load->model('Providers_Model');
            $this->load->model('Settings_Model');

            $customer_id = $this->Customers_Model->add($customer_data);
            $appointment_data['id_users_customer'] = $customer_id; 
            $appointment_data['id']     = $this->Appointments_Model->add($appointment_data);
            $appointment_data['hash']   = $this->Appointments_Model
                    ->get_value('hash', $appointment_data['id']);

            // Send an email to the customer with the appointment info.
            $this->load->library('Notifications');
            try {
                if (!$post_data['manage_mode']) {
                    $customer_title = 'Your appointment has been successfully booked!';
                    $provider_title = 'A new appointment has been added to your plan.';
                } else {
                    $customer_title = 'Appointment Changes Saved Successfully!';
                    $provider_title = 'Appointment Details Have Changed';
                }
                
                $this->notifications->send_book_success(
                        $customer_data, $appointment_data, $customer_title);
                $this->notifications->send_new_appointment(
                        $customer_data, $appointment_data, $provider_title);
                
            } catch (NotificationException $not_exc) {
                $view_data['notification_error'] = '<br><br>' 
                        . '<pre>An unexpected error occured while sending you an ' 
                        . 'email. Please backup the appointment details so that ' 
                        . 'you can restore them later. <br><br>Error: <br>' 
                        . $not_exc->getMessage() . '</pre>';
            }
            
            // Synchronize the appointment with the providers plan, if the 
            // google sync option is enabled.
            $google_sync = $this->Providers_Model->get_setting('google_sync', 
                    $appointment_data['id_users_provider']);
            
            if ($google_sync == TRUE) {
                $google_token = $this->Providers_Model->get_setting('google_token',
                        $appointment_data['id_users_provider']);

                // Authenticate the token. If it isn't valid, the sync operation cannot
                // be completed.
                $this->load->library('google_sync');
                
                if ($this->google_sync->authenticate($google_token) === TRUE) {
                    if ($manage_mode === FALSE) { 
                        // Add appointment to Google Calendar.
                        $this->google_sync->add_appointment($appointment_data['id']);
                    } else {
                        // Update appointment to Google Calendar.
                        $this->google_sync->update_appointment($appointment_data['id']);
                    }
                }
            }  

            // Load the book success view.
            $service_data   = $this->Services_Model->get_row($appointment_data['id_services']);
            $provider_data  = $this->Providers_Model->get_row($appointment_data['id_users_provider']);
            $company_name   = $this->Settings_Model->get_setting('company_name');
            
            $view_data = array(
                'appointment_data'  => $appointment_data,
                'service_data'      => $service_data,
                'provider_data'     => $provider_data,
                'company_name'      => $company_name
            );
            
            $this->load->view('appointments/book_success', $view_data);
        }   
    }
    
    /**
     * Cancel an existing appointment. 
     * 
     * This method removes an appointment from the company's schedule.
     * In order for the appointment to be deleted, the hash string must
     * be provided. The customer can only cancel the appointment if the
     * edit time period is not over yet.
     * 
     * @param string $appointment_hash This is used to distinguish the 
     * appointment record.
     */
    public function cancel($appointment_hash) {
        try {
            $this->load->model('Appointments_Model');
            $this->load->model('Providers_Model');
            $this->load->model('Customers_Model');
            
            // Check whether the appointment hash exists in the database.
            $records = $this->Appointments_Model->get_batch(array('hash' => $appointment_hash));
            if (count($records) == 0) {
                throw new Exception('No record matches the provided hash.');
            }
            
            $appointment_data = $records[0];
            
            // Delete the appointment from the database.
            if (!$this->Appointments_Model->delete($appointment_data['id'])) {
                throw new Exception('Appointment could not be deleted from the database.');
            }
            
            // Send notification emails to the customer and provider.
            $provider_email = $this->Providers_Model->get_value('email', 
                    $appointment_data['id_users_provider']);
            $customer_email = $this->Customers_Model->get_value('email', 
                    $appointment_data['id_users_customer']);
            
            $this->load->library('Notifications');
            $this->notifications->send_cancel_appointment($appointment_data, $provider_email);
            $this->notifications->send_cancel_appointment($appointment_data, $customer_email);
            
            // Delete the appointment from Google Calendar, if it is synced.
            if ($appointment_data['id_google_calendar'] != NULL) {
                $google_sync = $this->Providers_Model->get_setting('google_sync', 
                        $appointment_data['id_users_provider']);
                
                if ($google_sync == TRUE) {
                    $this->load->library('google_sync');
                    
                    // Get the provider's refresh token and try to authenticate the 
                    // Google Calendar API usage. 
                    $google_token = $this->Providers_Model->get_setting('google_token',
                            $appointment_data['id_users_provider']);
                    
                    if ($this->google_sync->authendicate($google_token) === TRUE) {
                        $this->google_sync->delete_appointment($appointment_data['id']);
                    }
                }
            } 
        } catch(Exception $exc) {
            // Display the error message to the customer.
            $view_data['error_message'] = $exc->getMessage();
        }
        
        $this->load->view('appointments/cancel');
    }
    
    /**
     * [AJAX] Get the available appointment hours for the given date.
     * 
     * This method answers to an AJAX request. It calculates the 
     * available hours for the given service, provider and date.
     * 
     * @param array $_POST['post_data'] An associative array that 
     * contains the user selected 'service_id', 'provider_id', 
     * 'selected_date' and 'service_duration' in minutes.
     * @return Returns a json object with the available hours.
     */
    public function ajax_get_available_hours() {
        $this->load->model('Providers_Model');
        $this->load->model('Appointments_Model');
        $this->load->model('Settings_Model');
        
        // Get the provider's working plan and reserved appointments.        
        $working_plan = json_decode($this->Providers_Model
                ->get_setting('working_plan', $_POST['provider_id']), true);
        
        $where_clause = array(
            'DATE(start_datetime)'  => date('Y-m-d', strtotime($_POST['selected_date'])),
            'id_users_provider'     => $_POST['provider_id']
        );
       
        $reserved_appointments = $this->Appointments_Model->get_batch($where_clause);
        
        if ($_POST['manage_mode'] === 'true') {
            // Current record id shouldn't be included as reserved time, when the
            // manage mode is true.
            foreach($reserved_appointments as $index=>$appointment) {
                if ($appointment['id'] == $_POST['appointment_id']) {
                    unset($reserved_appointments[$index]);
                }
            }
        }

        // Find the empty spaces on the plan. The first split between 
        // the plan is due to a break (if exist). After that every reserved 
        // appointment is considered to be a taken space in the plan.
        $sel_date_working_plan = $working_plan[strtolower(date('l', 
                strtotime($_POST['selected_date'])))];
        $empty_spaces_with_breaks = array();
        
        if (isset($sel_date_working_plan['breaks'])) {
            foreach($sel_date_working_plan['breaks'] as $index=>$break) {
                // Split the working plan to available time periods that do not
                // contain the breaks in them.
                $last_break_index = $index - 1;
                
                if (count($empty_spaces_with_breaks) === 0) {
                    $start_hour = $sel_date_working_plan['start'];
                    $end_hour = $break['start'];
                } else {
                    $start_hour = $sel_date_working_plan['breaks'][$last_break_index]['end'];
                    $end_hour = $break['start'];
                }
                
                $empty_spaces_with_breaks[] = array(
                                        'start' => $start_hour,
                                        'end'   => $end_hour
                                    );
            }
            
            // Add the space from the last break to the end of the day.
            $empty_spaces_with_breaks[] = array(
                'start' => $sel_date_working_plan['breaks'][$index]['end'],
                'end'   => $sel_date_working_plan['end']
            );
        }
        // PROBLEM
        // Break the empty spaces with the reserved appointments.
        $empty_spaces_with_appointments = array();
        if (count($reserved_appointments) > 0) {
            foreach($empty_spaces_with_breaks as $space) {
                foreach($reserved_appointments as $index=>$appointment) {
                    $appointment_start  = date('H:i', strtotime($appointment['start_datetime']));
                    $appointment_end    = date('H:i', strtotime($appointment['end_datetime']));
                    $space_start        = date('H:i', strtotime($space['start']));
                    $space_end          = date('H:i', strtotime($space['end']));
                    
                    if ($space_start < $appointment_start && $space_end > $appointment_end) {
                        // Current appointment is within the current empty space. So 
                        // we need to break the empty space into two other spaces that 
                        // don't include the appointment.
                        $empty_spaces_with_appointments[] = array(
                                                    'start' => $space_start,
                                                    'end'   => $appointment_start
                                                );
                        $empty_spaces_with_appointments[] = array(
                                                    'start' => $appointment_end,
                                                    'end'   => $space_end
                                                );
                    } else {
                        // Check if there are any other appointments between this 
                        // time space. If not, it is going to be added as it is.
                        $found = FALSE;
                        foreach($reserved_appointments as $appt) {
                            $appt_start  = date('H:i', strtotime($appt['start_datetime']));
                            $appt_end    = date('H:i', strtotime($appt['end_datetime']));
                            if ($space_start < $appt_start && $space_end > $appt_end) {
                                $found = TRUE;
                            }
                        }
                        
                        // It is also necessary to check that this time period doesn't
                        // already exist in the "$empty_spaces_with_appointments" array.
                        $empty_space = array(
                                            'start' => $space_start,
                                            'end'   => $space_end
                                        );
                        $already_exist = in_array($empty_space, $empty_spaces_with_appointments);
                        if ($found === FALSE && $already_exist === FALSE) {
                            $empty_spaces_with_appointments[] = $empty_space;
                        }
                    }
                }
            }
        } else {
            $empty_spaces_with_appointments = $empty_spaces_with_breaks;
        }
        
        $empty_spaces = $empty_spaces_with_appointments;
        
        // Calculate the available appointment hours for the given date. 
        // The empty spaces are broken down to 15 min and if the service
        // fit in each quarter then a new available hour is added to the
        // $available hours array.
        $available_hours = array();
        
        foreach($empty_spaces as $space) {
            $start_hour = new DateTime($_POST['selected_date'] . ' ' . $space['start']);
            $end_hour   = new DateTime($_POST['selected_date'] . ' ' . $space['end']);
            
            $minutes = $start_hour->format('i');
            
            if ($minutes % 15 != 0) {
                // Change the start hour of the current space in order to be
                // on of the following: 00, 15, 30, 45.
                if ($minutes < 15) {
                    $start_hour->setTime($start_hour->format('H'), 15);
                } else if ($minutes < 30) {
                    $start_hour->setTime($start_hour->format('H'), 30);
                } else if ($minutes < 45) {
                    $start_hour->setTime($start_hour->format('H'), 45);
                } else {
                    $start_hour->setTime($start_hour->format('H') + 1, 00);
                }
            }
            
            $curr_hour  = $start_hour;
            
            $diff = $curr_hour->diff($end_hour);
            while(($diff->h * 60 + $diff->i) > intval($_POST['service_duration'])) {
                $available_hours[] = $curr_hour->format('H:i');
                $curr_hour->add(new DateInterval("PT15M"));
                $diff = $curr_hour->diff($end_hour);
            }
        }
        
        // If the selected date is today, remove past hours. It is important 
        // include the timeout before booking that is set in the backoffice
        // the system. Normally we might want the customer to book an appointment
        // that is at least half or one hour from now. The setting is stored in 
        // minutes.
        if (date('m/d/Y', strtotime($_POST['selected_date'])) == date('m/d/Y')) {
            if ($_POST['manage_mode'] === 'true') {
                $book_advance_timeout = 0;
            } else {
                $book_advance_timeout = $this->Settings_Model
                        ->get_setting('book_advance_timeout');
            }
            
            foreach($available_hours as $index=>$value) {
                $available_hour = strtotime($value);
                $current_hour   = strtotime('+' . $book_advance_timeout 
                        . ' minutes', strtotime('now'));
        
                if ($available_hour <= $current_hour) {
                    unset($available_hours[$index]);
                }
            }
        }
        
        $available_hours = array_values($available_hours);
        
        echo json_encode($available_hours);
    }
}

/* End of file appointments.php */
/* Location: ./application/controllers/appointments.php */