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
			$this->RegisterPropertyInteger ("SoC",0) ; // ist eine ID, daher ein Integer

			$this->RegisterPropertyInteger ("Switch",0) ; // ist eine ID, daher ein Integer
			
			
			$this->RegisterVariableString("Controlstate", "Controlstate", "") ;
			
		}


		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();

			$ID_Humidity = $this->ReadPropertyInteger('Humidity'); // ist eine ID, daher ein Integer
			$ID_RoomPresence = $this->ReadPropertyInteger('RoomPrecence'); // ist eine ID, daher ein Integer
			$ID_WindowState = $this->ReadPropertyInteger('WindowState'); // ist eine ID, daher ein Integer
			$ID_SoC = $this->ReadPropertyInteger('SoC'); // ist eine ID, daher ein Integer

			$ID_Switch = $this->ReadPropertyInteger('Switch'); // ist eine ID, daher ein Integer
			

			if (!IPS_VariableExists($ID_Humidity) || !IPS_VariableExists($ID_RoomPresence) 
				|| !IPS_VariableExists($ID_WindowState) || !IPS_VariableExists($ID_SoC)
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
			$this->RegisterMessage($ID_WindowState, VM_UPDATE);
			$this->RegisterMessage($ID_SoC, VM_UPDATE);

			//Initial Control
			$this->Control();

		}

		public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
		{
			$this->SendDebug('Sender ' . $SenderID, 'Message ' . $Message, 0);
			if ($Message === VM_UPDATE) {
				$this->Control();
			}
		}

		private function Control(): bool
		{	
			$ID_Humidity = $this->ReadPropertyInteger('Humidity'); // ist eine ID, daher ein Integer
			$ID_RoomPresence = $this->ReadPropertyInteger('RoomPrecence'); // ist eine ID, daher ein Integer
			$ID_WindowState = $this->ReadPropertyInteger('WindowState'); // ist eine ID, daher ein Integer
			$ID_SoC = $this->ReadPropertyInteger('SoC'); // ist eine ID, daher ein Integer
			
			$ID_Switch = $this->ReadPropertyInteger('Switch'); // ist eine ID, daher ein Integer
			
			

			$Humidity = GetValue($ID_Humidity);
			$RoomPresence = GetValue($ID_RoomPresence);
			$WindowState = GetValue($ID_WindowState);
			$SoC = GetValue($ID_SoC);
			
			$Switch_State = GetValue($ID_Switch);
			

			if ($RoomPresence)
			{   
				$humidity_extrem = 70;
				
				$humidity_max = 64;
				$humidity_min = 58;
			}
			else // keiner im Raum
			{   
				$humidity_extrem = 66;
			
				$humidity_max = 58;
				$humidity_min = 52;
			}
			
			$SoC_min_Level = 90;
			/*if ($action == 1 ) // Morgens
			{
				$SoC_min_Level = 30;
			}
			else if ($action == 2 ) //Tagsüber
			{
				$SoC_min_Level = 80;
			}*/
			
			
			$trockner_control_state = $this->GetValue('Controlstate');
			//$Luft_trocknen = getvalue( IPS_GetObjectIDByIdent($ident_State, $id_dryer_switch)  );
			
			$Luft_trocknen = true;
			if ($trockner_control_state == "Trockner_off")
			{
				$Luft_trocknen = false;
			}
			
			
			switch ($trockner_control_state)
			{
				case "Trockner_off":
				{
					//if ( ($_IPS['VARIABLE'] == $id_Luftfeuchtigkeit) or ($_IPS['VARIABLE'] == $id_solar_battery) )
					{
						if ( $Humidity > $humidity_extrem )
						{
							$Luft_trocknen = true;
							$trockner_control_state = "Trockner_on_extrem";
						}
						else if ( ( $Humidity > $humidity_max ) and ($SoC >= $SoC_min_Level) )
						{
							$Luft_trocknen = true;
							$trockner_control_state = "Trockner_on_normal";
						}
					}
		
					//if ( ($_IPS['VARIABLE'] == $id_raum_anwesend) and ($_IPS['VALUE'] == false) )
					{
						if (  (($Humidity <= $humidity_max) or ($Humidity >= $humidity_min)) and ($SoC >= $SoC_min_Level) )
						{
							$Luft_trocknen = true;
							$trockner_control_state = "Trockner_on_normal";
						}
					}
				}
				break;
		
		
				case "Trockner_on_normal":
				{
					//if ( ($_IPS['VARIABLE'] == $id_Luftfeuchtigkeit) or ($_IPS['VARIABLE'] == $id_solar_battery) )
					{
						if ( ($Humidity < $humidity_min) or  ($SoC < ($SoC_min_Level - 5)) )
						{
							$Luft_trocknen = false;
							$trockner_control_state = "Trockner_off";
						}
					}
				
					//if ( ($_IPS['VARIABLE'] == $id_raum_anwesend) and ($_IPS['VALUE'] == true) )
					{
						if (  (($Humidity <= $humidity_max) or ($Humidity >= $humidity_min))  )
						{
							$Luft_trocknen = false;
							$trockner_control_state = "Trockner_off";
						}
					}
				
				}
				break;
		
				case "Trockner_on_extrem":
				{
					//if ( ($_IPS['VARIABLE'] == $id_Luftfeuchtigkeit) or ($_IPS['VARIABLE'] == $id_solar_battery)  or  ($_IPS['VARIABLE'] == $id_raum_anwesend)  )
					{
						if ( $Humidity < $humidity_max)
						{
							if ( ($Humidity > $humidity_min)  and ($SoC >= $SoC_min_Level) )
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
		
			} // end Switch
			
			$this->SetValue('Controlstate', $trockner_control_state );
			 
			if ( $WindowState != "geschloßen")
			{ 
				$Luft_trocknen = false;  
			};
			
			echo $ID_Switch; echo "Value"; echo $Luft_trocknen;
			RequestAction($ID_Switch, $Luft_trocknen );

			return true;
		}

	}