<?php
	if(!file_exists('vendor')) {
		throw new Exception('No vendor directory detected, please run install.sh first.');
	}

	require 'vendor/autoload';