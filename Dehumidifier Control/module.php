<?php

declare(strict_types=1);
	class DehumidifierControl extends IPSModule
	{
		public function Create()
		{
			//Never delete this line!
			parent::Create();
			
			// Externe Inputs
			$this->RegisterPropertyInteger ("Humidity", 0) ; // ist eine ID, daher ein Integer
			$this->RegisterPropertyInteger ("RoomPrecence",0) ; // ist eine ID, daher ein Integer
			$this->RegisterPropertyInteger ("WindowState",0) ; // ist eine ID, daher ein Integer
			$this->RegisterPropertyInteger ("Release_State",0) ; // ist eine ID, daher ein Integer

			$this->RegisterPropertyInteger ("ExtremHumidity_Present", 70) ;
			$this->RegisterPropertyInteger ("MaxHumidity_Present", 64) ; 
			$this->RegisterPropertyInteger ("MinHumidity_Present", 58) ; 

			$this->RegisterPropertyInteger ("ExtremHumidity_Absend", 66) ;
			$this->RegisterPropertyInteger ("MaxHumidity_Absend", 58) ; 
			$this->RegisterPropertyInteger ("MinHumidity_Absend", 52) ; 

			$this->RegisterPropertyInteger ("Switch",0) ; // ist eine ID, daher ein Integer
			
			// State of Statemachine
			$this->RegisterAttributeString("Controlstate", "Start nach Create") ;
		}


		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();

			$ID_Humidity = $this->ReadPropertyInteger('Humidity'); // ist eine ID, daher ein Integer
			$ID_RoomPresence = $this->ReadPropertyInteger('RoomPrecence'); // ist eine ID, daher ein Integer
			$ID_WindowState = $this->ReadPropertyInteger('WindowState'); // ist eine ID, daher ein Integer
			$ID_Relases_State = $this->ReadPropertyInteger('Release_State'); // ist eine ID, daher ein Integer

			$ID_Switch = $this->ReadPropertyInteger('Switch'); // ist eine ID, daher ein Integer
			

			if (!IPS_VariableExists($ID_Humidity) || !IPS_VariableExists($ID_RoomPresence) 
				|| !IPS_VariableExists($ID_Relases_State)
				|| !IPS_VariableExists($ID_Switch) 
				) 
			{
				$this->SetStatus(200); //One of the Variable is missing
				return;
			} else 
			{
				$this->SetStatus(102); //All right
			}
			
			//Messages
        	//Unregister all messages
        	foreach ($this->GetMessageList() as $senderID => $messages) 
			{
            	foreach ($messages as $message) 
				{
                	$this->UnregisterMessage($senderID, $message);
            	}
        	}

			//Register necessary messages
			$this->RegisterMessage($ID_Humidity, VM_UPDATE);
			$this->RegisterMessage($ID_RoomPresence, VM_UPDATE);
			if (IPS_VariableExists($ID_WindowState))
			{
				$this->RegisterMessage($ID_WindowState, VM_UPDATE);
			}
			$this->RegisterMessage($ID_Relases_State, VM_UPDATE);

			//Initial Control
			$this->Control(0,0);

		}

		public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
		{
			$this->SendDebug('Sender ' . $SenderID, 'Message ' . $Message, 0);
			if ($Message === VM_UPDATE) {
				$this->Control($SenderID, $Message);
			}
		}

		private function Control($SenderID, $Message ): bool
		{	
			//echo 'Sender ' . $SenderID, 'Message ' . $Message;
			
			
			$ID_Humidity = $this->ReadPropertyInteger('Humidity'); // ist eine ID, daher ein Integer
			$ID_RoomPresence = $this->ReadPropertyInteger('RoomPrecence'); // ist eine ID, daher ein Integer
			$ID_WindowState = $this->ReadPropertyInteger('WindowState'); // ist eine ID, daher ein Integer
			$ID_Relases_State = $this->ReadPropertyInteger('Release_State'); // ist eine ID, daher ein Integer
			
			$ID_Switch = $this->ReadPropertyInteger('Switch'); // ist eine ID, daher ein Integer

			$Humidity = GetValue($ID_Humidity);
			$RoomPresence = GetValue($ID_RoomPresence);
			$Relases_State = GetValue($ID_Relases_State);

			if (IPS_VariableExists($ID_WindowState))
			{
				$WindowState = GetValue($ID_WindowState);
			}
			else
			{
				$WindowState = "geschloßen";
			}
			
			


			if ($RoomPresence)
			{   
				$humidity_extrem = $this->ReadPropertyInteger("ExtremHumidity_Present");
				
				$humidity_max = $this->ReadPropertyInteger("MaxHumidity_Present");
				$humidity_min = $this->ReadPropertyInteger("MinHumidity_Present");
			}
			else // keiner im Raum
			{   
				$humidity_extrem = $this->ReadPropertyInteger("ExtremHumidity_Absend");
			
				$humidity_max = $this->ReadPropertyInteger("MaxHumidity_Absend");
				$humidity_min = $this->ReadPropertyInteger("MinHumidity_Absend");
			}
			
			
			$trockner_control_state = $this->ReadAttributeString('Controlstate');
			//$Luft_trocknen = getvalue( IPS_GetObjectIDByIdent($ident_State, $id_dryer_switch)  );
			
			$Luft_trocknen = true;
			if ($trockner_control_state == "Trockner_off")
			{
				$Luft_trocknen = false;
			}
			
			$this->SendDebug('old State '. $trockner_control_state, 'Feuchte'. $Humidity, 0 );
			
			switch ($trockner_control_state)
			{
				case "Trockner_off":
				{
					if ( ($SenderID == $ID_Humidity) or ($SenderID  == $ID_Relases_State) )
					{
						if ( $Humidity > $humidity_extrem )
						{
							$Luft_trocknen = true;
							$trockner_control_state = "Trockner_on_extrem";
						}
						else if ( ( $Humidity > $humidity_max ) and ($Relases_State) )
						{
							$Luft_trocknen = true;
							$trockner_control_state = "Trockner_on_normal";
						}
					}
		
					if ($SenderID == $ID_RoomPresence)  //and ($_IPS['VALUE'] == false) )
					{
						if (!$RoomPresence)
						{
							if (  (($Humidity <= $humidity_max) or ($Humidity >= $humidity_min)) and ($Relases_State) )
							{
								$Luft_trocknen = true;
								$trockner_control_state = "Trockner_on_normal";
							}
						}
					}
				}
				break;
		
		
				case "Trockner_on_normal":
				{
					if ( ($SenderID == $ID_Humidity) or ($SenderID  == $ID_Relases_State) )
					{
						if ( ($Humidity < $humidity_min) or  (!$Relases_State) )
						{
							$Luft_trocknen = false;
							$trockner_control_state = "Trockner_off";
						}
					}
				
					if ($SenderID == $ID_RoomPresence)  //and ($_IPS['VALUE'] == true) )
					{
						if ($RoomPresence)
						{
							if (  (($Humidity <= $humidity_max) and ($Humidity >= $humidity_min))  )
							{
								$Luft_trocknen = false;
								$trockner_control_state = "Trockner_off";
							}
						}
					}
				}
				break;
		
				case "Trockner_on_extrem":
				{
					//if ( ($_IPS['VARIABLE'] == $id_Luftfeuchtigkeit) or ($_IPS['VARIABLE'] == $id_solar_battery)  or  ($_IPS['VARIABLE'] == $id_raum_anwesend)  )
					//if ( ($SenderID == $ID_Humidity) or ($SenderID  == $ID_SoC) )
					{
						if ( ($Humidity < ($humidity_extrem-10)) or ($Humidity < ($humidity_max)) )
						{
							if ( ($Humidity > $humidity_min)  and (!$Relases_State) )
							{
								$Luft_trocknen = true;
								$trockner_control_state = "Trockner_on_normal";
							}
							else
							{
								$Luft_trocknen = false;
								$trockner_control_state = "Trockner_off";
							}
						}
					}
				}
				break;
					
				default:
				{
					$Luft_trocknen = false;
					$trockner_control_state = "Trockner_off";
				}
		
			} // end  Switch
		
			$this->SendDebug('new State '. $trockner_control_state, 'Feuchte'. $Humidity, 0 );
		
			$this->WriteAttributeString('Controlstate', $trockner_control_state );
			//$this->LogMessage(" Controlstate ". $ID_Switch . " ".  IPS_GetName( $ID_Switch) . " ".  $trockner_control_state, KL_NOTIFY);

			if ( $WindowState != "geschloßen")
			{ 
				$Luft_trocknen = false;  
			};

			$feedback = requestAction($ID_Switch, $Luft_trocknen);
			if ($feedback == false)
			{
				$this->LogMessage(" Switch ERROR ". $ID_Switch . " ".  IPS_GetName( $ID_Switch) . " ".  $trockner_control_state, KL_WARNING);
			}
			

			return true;
		}

	}