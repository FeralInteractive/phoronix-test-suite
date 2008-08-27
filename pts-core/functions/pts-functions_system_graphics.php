<?php

/*
	Phoronix Test Suite
	URLs: http://www.phoronix.com, http://www.phoronix-test-suite.com/
	Copyright (C) 2008, Phoronix Media
	Copyright (C) 2008, Michael Larabel
	pts-functions_system_graphics.php: System functions related to graphics.

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

define("DEFAULT_VIDEO_RAM_CAPACITY", 128);

function graphics_frequency_string()
{
	// Report graphics frequency string
	if(IS_ATI_GRAPHICS)
		$freq = graphics_processor_stock_frequency();
	else
		$freq = graphics_processor_frequency();

	$freq_string = $freq[0] . "/" . $freq[1];

	if($freq_string == "0/0")
	{
		$freq_string = "";
	}
	else
	{
		$freq_string = " (" . $freq_string . "MHz)";
	}

	return $freq_string;
}
function graphics_processor_temperature()
{
	// Report graphics processor temperature
	$temp_c = -1;

	if(IS_NVIDIA_GRAPHICS)
	{
		$temp_c = read_nvidia_extension("GPUCoreTemp");
	}
	else if(IS_ATI_GRAPHICS)
	{
		$temp_c = read_ati_overdrive("Temperature");

		if($temp_c == -1)
			$temp_c = read_ati_extension("CoreTemperature");
	}

	if(empty($temp_c) || !is_numeric($temp_c))
		$temp_c = -1;

	return $temp_c;
}
function graphics_monitor_count()
{
	// Report number of connected/enabled monitors
	$monitor_count = 0;

	// First try reading number of monitors from xdpyinfo
	$monitor_count = count(read_xdpy_monitor_info());

	if($monitor_count == 0)
	{
		// Fallback support for ATI and NVIDIA if read_xdpy_monitor_info() fails
		if(IS_NVIDIA_GRAPHICS)
		{
			$enabled_displays = read_nvidia_extension("EnabledDisplays");

			switch($enabled_displays)
			{
				case "0x00010000":
					$monitor_count = 1;
					break;
				case "0x00010001":
					$monitor_count = 2;
					break;
				default:
					$monitor_count = 1;
					break;
			}
		}
		else if(IS_ATI_GRAPHICS)
		{
			$amdpcsdb_enabled_monitors = amd_pcsdb_parser("SYSTEM/BUSID-*/DDX,EnableMonitor");

			if(!is_array($amdpcsdb_enabled_monitors))
				$amdpcsdb_enabled_monitors = array($amdpcsdb_enabled_monitors);

			foreach($amdpcsdb_enabled_monitors as $enabled_monitor)
				foreach(explode(",", $enabled_monitor) as $monitor_connection)
					$monitor_count++;
		}
		else
			$monitor_count = 1;
	}

	return $monitor_count;
}
function graphics_monitor_layout()
{
	// Determine layout for multiple monitors
	$monitor_layout = array("CENTER");

	if(graphics_monitor_count() > 1)
	{
		$xdpy_monitors = read_xdpy_monitor_info();
		$hit_0_0 = false;
		for($i = 0; $i < count($xdpy_monitors); $i++)
		{
			$monitor_position = explode("@", $xdpy_monitors[$i]);
			$monitor_position = trim($monitor_position[1]);
			$monitor_position_x = substr($monitor_position, 0, strpos($monitor_position, ","));
			$monitor_position_y = substr($monitor_position, strpos($monitor_position, ",") + 1);

			if($monitor_position == "0,0")
			{
				$hit_0_0 = true;
			}
			else if($monitor_position_x > 0 && $monitor_position_y == 0)
			{
				if($hit_0_0 == false)
					array_push($monitor_layout, "LEFT");
				else
					array_push($monitor_layout, "RIGHT");
			}
			else if($monitor_position_x == 0 && $monitor_position_y > 0)
			{
				if($hit_0_0 == false)
					array_push($monitor_layout, "UPPER");
				else
					array_push($monitor_layout, "LOWER");
			}
		}

		if(count($monitor_layout) == 1)
		{
			// Something went wrong with xdpy information, go to fallback support
			if(IS_ATI_GRAPHICS)
			{
				$amdpcsdb_monitor_layout = amd_pcsdb_parser("SYSTEM/BUSID-*/DDX,DesktopSetup");

				if(!is_array($amdpcsdb_monitor_layout))
					$amdpcsdb_monitor_layout = array($amdpcsdb_monitor_layout);

				foreach($amdpcsdb_monitor_layout as $card_monitor_configuration)
				{
					switch($card_monitor_configuration)
					{
						case "horizontal":
							array_push($monitor_layout, "RIGHT");
							break;
						case "horizontal,reverse":
							array_push($monitor_layout, "LEFT");
							break;
						case "vertical":
							array_push($monitor_layout, "ABOVE");
							break;
						case "vertical,reverse":
							array_push($monitor_layout, "BELOW");
							break;
					}
				}
			}
		}
	}

	return implode(",", $monitor_layout);
}
function graphics_monitor_resolutions()
{
	// Determine resolutions for each monitor
	$resolutions = array();

	if(graphics_monitor_count() == 1)
	{
		array_push($resolutions, current_screen_resolution());
	}
	else
	{
		foreach(read_xdpy_monitor_info() as $monitor_line)
		{
			$this_resolution = substr($monitor_line, strpos($monitor_line, ":") + 2);
			$this_resolution = substr($this_resolution, 0, strpos($this_resolution, " "));
			array_push($resolutions, $this_resolution);
		}
	}

	return implode(",", $resolutions);
}
function graphics_antialiasing_level()
{
	// Determine AA level if over-rode
	$aa_level = "";

	if(IS_NVIDIA_GRAPHICS)
	{
		$nvidia_fsaa = read_nvidia_extension("FSAA");

		switch($nvidia_fsaa)
		{
			case 1:
				$aa_level = "2x Bilinear";
				break;
			case 5:
				$aa_level = "4x Bilinear";
				break;
			case 7:
				$aa_level = "8x";
				break;
			case 8:
				$aa_level = "16x";
				break;
			case 10:
				$aa_level = "8xQ";
				break;
			case 12:
				$aa_level = "16xQ";
				break;
		}
	}
	else if(IS_ATI_GRAPHICS)
	{
		$ati_fsaa = read_amd_pcsdb("OpenGL,AntiAliasSamples");

		if(!empty($ati_fsaa))
		{
			switch($ati_fsaa)
			{
				case "0x00000002":
					$aa_level = "2x";
					break;
				case "0x00000004":
					$aa_level = "4x";
					break;
				case "0x00000008":
					$aa_level = "8x";
					break;
			}
		}
	}

	return $aa_level;
}
function graphics_anisotropic_level()
{
	// Determine AF level if over-rode
	$af_level = "";

	if(IS_NVIDIA_GRAPHICS)
	{
		$nvidia_af = read_nvidia_extension("LogAniso");

		switch($nvidia_af)
		{
			case 1:
				$af_level = "2x";
				break;
			case 2:
				$af_level = "4x";
				break;
			case 3:
				$af_level = "8x";
				break;
			case 4:
				$af_level = "16x";
				break;
		}
	}
	else if(IS_ATI_GRAPHICS)
	{
		$ati_af = read_amd_pcsdb("OpenGL,AnisoDegree");

		if(!empty($ati_af))
		{
			switch($ati_af)
			{
				case "0x00000002":
					$af_level = "2x";
					break;
				case "0x00000004":
					$af_level = "4x";
					break;
				case "0x00000008":
					$af_level = "8x";
					break;
				case "0x00000010":
					$af_level = "16x";
					break;
			}
		}
	}

	return $af_level;
}
function set_nvidia_extension($attribute, $value)
{
	// Sets an object in NVIDIA's NV Extension
	if(!IS_NVIDIA_GRAPHICS)
		return;

	$info = shell_exec("nvidia-settings --assign " . $attribute . "=" . $value . " 2>&1");
}
function set_amd_pcsdb($attribute, $value)
{
	// Sets a value for AMD's PCSDB, Persistent Configuration Store Database
	if(!IS_ATI_GRAPHICS)
		return;

	if(!empty($value))
	{
		$DISPLAY = substr(getenv("DISPLAY"), 1, 1);
		
		$info = shell_exec("DISPLAY=:" . $DISPLAY . " aticonfig --set-pcs-val=" . $attribute . "," . $value . "  2>&1");
	}
}
function sort_available_modes($modes)
{
	// Sort graphics card resolution modes
	$mode_pixel_counts = array();
	$sorted_modes = array();

	foreach($modes as $this_mode)
		if(count($this_mode) == 2)
			array_push($mode_pixel_counts, $this_mode[0] * $this_mode[1]);
		else
			unset($this_mode);

	sort($mode_pixel_counts);

	for($i = 0; $i < count($mode_pixel_counts); $i++)
	{
		$hit = false;
		for($j = 0; $j < count($modes) && !$hit; $j++)
		{
			if($modes[$j] != null && ($modes[$j][0] * $modes[$j][1]) == $mode_pixel_counts[$i])
			{
				array_push($sorted_modes, $modes[$j]);
				$modes[$j] = null;
				$hit = true;
			}
		}
	}

	return $sorted_modes;
}
function xrandr_available_modes()
{
	// XRandR available modes
	$info = shell_exec("xrandr 2>&1");
	$xrandr_lines = array_reverse(explode("\n", $info));
	$available_modes = array();

	$supported_ratios = array(1.60, 1.25, 1.33);
	$ignore_modes = array(array(832, 624), array(1152, 864), array(1792, 1344), array(1800, 1440), array(1856, 1392), array(2048, 1536));

	foreach($xrandr_lines as $xrandr_mode)
	{
		if(($cut_point = strpos($xrandr_mode, "(")) > 0)
			$xrandr_mode = substr($xrandr_mode, 0, $cut_point);

		$res = explode("x", $xrandr_mode);

		if(count($res) == 2)
		{
			$res[0] = trim($res[0]);
			$res[1] = trim($res[1]);

			$res[0] = substr($res[0], strrpos($res[0], " "));
			$res[1] = substr($res[1], 0, strpos($res[1], " "));

			if(is_numeric($res[0]) && is_numeric($res[1]) && $res[0] >= 800 && $res[1] >= 600)
			{
				$ratio = pts_trim_double($res[0] / $res[1], 2);
				$this_mode = array($res[0], $res[1]);

				if(in_array($ratio, $supported_ratios) && !in_array($this_mode, $ignore_modes))
					array_push($available_modes, $this_mode);
			}
		}
	}

	if(count($available_modes) < 2)
	{
		$available_modes = array(array(800, 600), array(1024, 768), array(1280, 1024), array(1680, 1050), array(1600, 1200), array(1920, 1080));
	}
	else
	{
		$available_modes = sort_available_modes($available_modes);
	}

	return $available_modes;
}
function xrandr_screen_resolution()
{
	// Find the current screen resolution using XRandR
	$info = shell_exec("xrandr 2>&1 | grep \"*\"");

	if(strpos($info, "*") !== FALSE)
	{
		$res = explode("x", $info);
		$res[0] = trim($res[0]);
		$res[1] = trim($res[1]);

		$res[0] = substr($res[0], strrpos($res[0], " "));
		$res[1] = substr($res[1], 0, strpos($res[1], " "));

		if(is_numeric($res[0]) && is_numeric($res[1]))
		{
			$info = array();
			array_push($info, trim($res[0]), trim($res[1]));
		}
		else
			$info = "";
	}

	if(empty($info))
	{
		if(IS_NVIDIA_GRAPHICS && ($nvidia = read_nvidia_extension("FrontendResolution")) != "")
		{
			$info = explode(",", $nvidia);
		}
		else
			$info = array("Unknown", "Unknown");
	}

	return $info;
}
function current_screen_resolution()
{
	// Return the current screen resolution
	if(($width = current_screen_width()) != "Unknown" && ($height = current_screen_height()) != "Unknown")
		$resolution = $width . "x" . $height;
	else
		$resolution = "Unknown";

	return $resolution;
}
function current_screen_width()
{
	// Current screen width
	$resolution = xrandr_screen_resolution();
	return $resolution[0];
}
function current_screen_height()
{
	// Current screen height
	$resolution = xrandr_screen_resolution();
	return $resolution[1];
}
function graphics_processor_stock_frequency()
{
	// Graphics processor stock frequency
	$core_freq = 0;
	$mem_freq = 0;

	if(IS_NVIDIA_GRAPHICS) // NVIDIA GPU
	{
		$nv_freq = read_nvidia_extension("GPUDefault3DClockFreqs");

		$nv_freq = explode(',', $nv_freq);
		$core_freq = $nv_freq[0];
		$mem_freq = $nv_freq[1];
	}
	else if(IS_ATI_GRAPHICS) // ATI GPU
	{
		$od_clocks = read_ati_overdrive("CurrentPeak");

		if(is_array($od_clocks) && count($od_clocks) == 2) // ATI OverDrive
		{
			$core_freq = $od_clocks[0];
			$mem_freq = $od_clocks[1];
		}
		else // Fallback for ATI GPUs w/o OverDrive Support
		{
			$ati_freq = read_ati_extension("Stock3DFrequencies");
			$ati_freq = explode(",", $ati_freq);
			$core_freq = $ati_freq[0];
			$mem_freq = $ati_freq[1];
		}
	}

	if(!is_numeric($core_freq))
		$core_freq = 0;
	if(!is_numeric($mem_freq))
		$mem_freq = 0;

	return array($core_freq, $mem_freq);
}
function graphics_processor_frequency()
{
	// Graphics processor real/current frequency
	$core_freq = 0;
	$mem_freq = 0;

	if(IS_NVIDIA_GRAPHICS) // NVIDIA GPU
	{
		$nv_freq = read_nvidia_extension("GPUCurrentClockFreqs");

		$nv_freq = explode(',', $nv_freq);
		$core_freq = $nv_freq[0];
		$mem_freq = $nv_freq[1];
	}
	else if(IS_ATI_GRAPHICS) // ATI GPU
	{
		$od_clocks = read_ati_overdrive("CurrentClocks");

		if(is_array($od_clocks) && count($od_clocks) == 2) // ATI OverDrive
		{
			$core_freq = $od_clocks[0];
			$mem_freq = $od_clocks[1];
		}
		else // Fallback for ATI GPUs w/o OverDrive Support
		{
			$ati_freq = read_ati_extension("Current3DFrequencies");
			$ati_freq = explode(",", $ati_freq);
			$core_freq = $ati_freq[0];
			$mem_freq = $ati_freq[1];
		}
	}

	if(!is_numeric($core_freq))
		$core_freq = 0;
	if(!is_numeric($mem_freq))
		$mem_freq = 0;

	return array($core_freq, $mem_freq);
}
function graphics_processor_string()
{
	// Report graphics processor string
	$info = shell_exec("glxinfo 2>&1 | grep renderer");
	$video_ram = graphics_memory_capacity();

	if(($pos = strpos($info, "renderer string:")) > 0)
	{
		$info = substr($info, $pos + 16);
		$info = trim(substr($info, 0, strpos($info, "\n")));
	}
	else
		$info = "";

	if(IS_ATI_GRAPHICS)
	{
		$crossfire_status = amd_pcsdb_parser("SYSTEM/Crossfire/chain/*,Enable");
		$crossfire_card_count = 0;

		if(!is_array($crossfire_status))
			$crossfire_status = array($crossfire_status);

		for($i = 0; $i < count($crossfire_status); $i++)
			if($crossfire_status[$i] == "0x00000001")
				$crossfire_card_count += 2; // For now assume each chain is 2 cards, but proper way would be NumSlaves + 1				

		$adapters = read_amd_graphics_adapters();

		if(count($adapters) > 0)
		{
			if($video_ram > DEFAULT_VIDEO_RAM_CAPACITY)
				$video_ram = " " . $video_ram . "MB";
			else
				$video_ram = "";

			if($crossfire_card_count > 1 && $crossfire_card_count <= count($adapters))
			{
				$unique_adapters = array_unique($adapters);

				if(count($unique_adapters) == 1)
				{
					if(strpos($adapters[0], "X2") > 0 && $crossfire_card_count > 1)
						$crossfire_card_count -= 1;

					$info = $crossfire_card_count . " x " . $adapters[0] . $video_ram . " CrossFire";
				}
				else
					$info = implode(", ", $unique_adapters) . " CrossFire";
			}
			else
				$info = $adapters[0] . $video_ram;
		}
	}
	else if(IS_NVIDIA_GRAPHICS)
	{
		$sli_mode = read_nvidia_extension("SLIMode");

		if(!empty($sli_mode) && $sli_mode != "Off")
			$info .= " SLI";
	}

	if(empty($info) || strpos($info, "Mesa ") !== FALSE || $info == "Software Rasterizer")
	{
		$info_pci = read_pci("VGA compatible controller", false);

		if(!empty($info_pci) && $info_pci != "Unknown")
			$info = $info_pci;
	}

	if(strpos($info, "Unknown") !== FALSE)
	{
		$log_parse = shell_exec("cat /var/log/Xorg.0.log | grep Chipset");
		$log_parse = substr($log_parse, strpos($log_parse, "Chipset") + 8);
		$log_parse = substr($log_parse, 0, strpos($log_parse, "found"));

		if(strpos($log_parse, "ATI") !== FALSE || strpos($log_parse, "NVIDIA") !== FALSE || strpos($log_parse, "VIA") !== FALSE || strpos($log_parse, "Intel") !== FALSE)
			$info = $log_parse;

		if(($start_pos = strpos($info, " DRI ")) > 0)
			$info = substr($info, $start_pos + 5);
	}

	if(IS_BSD && $info == "Unknown")
	{
		$info = read_sysctl("dev.drm.0.%desc");

		if($info == "Unknown")
			$info = read_sysctl("dev.agp.0.%desc");
	}

	if(IS_NVIDIA_GRAPHICS && $video_ram > DEFAULT_VIDEO_RAM_CAPACITY && strpos($info, $video_ram) == FALSE)
	{
		$info .= " " . $video_ram . "MB";
	}

	$info = pts_clean_information_string($info);

	return $info;
}
function graphics_subsystem_version()
{
	// Find graphics subsystem version
	if(IS_SOLARIS)
		$info = shell_exec("X :0 -version 2>&1");
	else
		$info = shell_exec("X -version 2>&1");

	$pos = strrpos($info, "Release Date");
	$info = trim(substr($info, 0, $pos));

	if($pos === FALSE)
	{
		$info = "Unknown";
	}
	else if(($pos = strrpos($info, "(")) === FALSE)
	{
		$info = trim(substr($info, strrpos($info, " ")));
	}
	else
	{
		$info = trim(substr($info, strrpos($info, "Server") + 6));
	}

	return $info;
}
function graphics_memory_capacity()
{
	// Graphics memory capacity
	$video_ram = DEFAULT_VIDEO_RAM_CAPACITY;

	if(($vram = getenv("VIDEO_MEMORY")) != FALSE && is_numeric($vram) && $vram > DEFAULT_VIDEO_RAM_CAPACITY)
	{
		$video_ram = $vram;
	}
	else
	{
		if(IS_NVIDIA_GRAPHICS && ($NVIDIA = read_nvidia_extension("VideoRam")) > 0) // NVIDIA blob
		{
			$video_ram = $NVIDIA / 1024;
		}
		else if(is_file("/var/log/Xorg.0.log"))
		{
			// Attempt Video RAM detection using X log
			// fglrx driver reports video memory to: (--) fglrx(0): VideoRAM: XXXXXX kByte, Type: DDR
			// xf86-video-ati and xf86-video-radeonhd also report their memory information in a similar format

			$info = shell_exec("cat /var/log/Xorg.0.log | grep VideoRAM");

			if(empty($info))
				$info = shell_exec("cat /var/log/Xorg.0.log | grep \"Video RAM\"");

			if(($pos = strpos($info, "RAM:")) > 0)
			{
				$info = substr($info, $pos + 5);
				$info = substr($info, 0, strpos($info, " "));

				if($info > 65535)
					$video_ram = intval($info) / 1024;
			}
		}
	}

	return $video_ram;
}
function opengl_version()
{
	// OpenGL version
	$info = shell_exec("glxinfo 2>&1 | grep version");

	if(($pos = strpos($info, "OpenGL version string:")) === FALSE)
	{
		$info = "N/A";
	}
	else
	{
		$info = substr($info, $pos + 23);
		$info = trim(substr($info, 0, strpos($info, "\n")));
		$info = str_replace(array(" Release"), "", $info);
	}

	if(str_replace(array("NVIDIA", "ATI", "AMD", "Radeon", "Intel"), "", $info) == $info)
	{
		if(is_file("/proc/dri/0/name"))
		{
			$driver_info = file_get_contents("/proc/dri/0/name");
			$driver_info = substr($driver_info, 0, strpos($driver_info, ' '));
			$info .= " ($driver_info)";
		}
	}

	return $info;
}
function graphics_gpu_usage()
{
	// Determine GPU usage
	$gpu_usage = 0;

	if(IS_ATI_GRAPHICS)
	{
		$gpu_usage = read_ati_overdrive("GPUload");

		if($gpu_usage == -1) // OverDrive isn't supported on the ATI hardware or a supported driver isn't loaded
			$gpu_usage = read_ati_extension("GPUActivity");
	}

	return $gpu_usage;
}

?>
