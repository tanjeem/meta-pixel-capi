<?php
namespace Mpc;

class Autoloader {
	protected $prefixes = array();

	public function register() {
		spl_autoload_register( array( $this, 'loadClass' ) );
	}

	public function addNamespace( $prefix, $base_dir ) {
		$prefix = trim( $prefix, '\\' ) . '\\';
		$base_dir = rtrim( $base_dir, DIRECTORY_SEPARATOR ) . '/';
		$this->prefixes[$prefix] = $base_dir;
	}

	public function loadClass( $class ) {
		$prefix = $class;
		while ( false !== $pos = strrpos( $prefix, '\\' ) ) {
			$prefix = substr( $class, 0, $pos + 1 );
			$relative_class = substr( $class, $pos + 1 );
			$mapped_file = $this->loadMappedFile( $prefix, $relative_class );
			if ( $mapped_file ) {
				return $mapped_file;
			}
			$prefix = rtrim( $prefix, '\\' );
		}
		return false;
	}

	protected function loadMappedFile( $prefix, $relative_class ) {
		if ( isset( $this->prefixes[$prefix] ) ) {
			$base_dir = $this->prefixes[$prefix];
			$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
			if ( $this->requireFile( $file ) ) {
				return $file;
			}
		}
		return false;
	}

	protected function requireFile( $file ) {
		if ( file_exists( $file ) ) {
			require $file;
			return true;
		}
		return false;
	}
}
