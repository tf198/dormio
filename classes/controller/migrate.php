<?
/**
* Schema migration interface
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Lesser General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU Lesser General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
* @author Tris Forster <tris.701437@tfconsulting.com.au>
* @version 0.3
* @license http://www.gnu.org/licenses/lgpl.txt GNU Lesser General Public License v3
* @package dormio
*/

/**
* Bantam controller to generate and apply migration scripts.
* Stores info in dormio_migration table on default database.
* Migration scripts should be stored in the migrations folder under a module folder
* and be in the format <timestamp>_<model>.php.  You can auto-generate them using
* php index.php migrate generate <module> and edit them by hand.
*/
class Controller_Migrate {
  function __construct() {
    if(PHP_SAPI!='cli') throw new Exception("Command line only controller");
    $this->dormio = Dormio_Bantam::factory();
  }
  
  /**
  * Show usage info.
  */
  function index() {
    $usage = <<< EOF
Usage: php index.php migrate <command> [<args>]
  all                 Apply all migrations from enabled modules
  module <module>     Apply migrations for specified module
  generate <module>   Create migration scripts to get module to current models
  upgrade_db <model>  Generate code to upgrade a model table to the current schema
EOF;
    fputs(STDERR, $usage);
  }
  
  /**
  * Applies migrations from all enabled modules to bring the database up to current.
  */
  function all() {
    foreach(Bantam::instance()->paths as $path) {
      $this->module($path);
    }
  }
  
  /**
  * Applies migrations for a specific module
  * @param  string  $path   Path to module
  */
  function module($path) {
    $path = realpath($path);
    $migrations = glob($path . DIRECTORY_SEPARATOR . "migrations" . DIRECTORY_SEPARATOR . "*.php");
    
    // get the most recent applied migration
    try {
      $m = $this->dormio->manager('Dormio_Migration')->filter('module', '=', basename($path))->orderBy('-applied')->limit(1)->get();
      $last = $path . DIRECTORY_SEPARATOR . "migrations" . DIRECTORY_SEPARATOR . $m->file;
    } catch(Exception $e) {
      $last = "";
    }
    
    foreach($migrations as $migration) {
      if($migration > $last) $this->_migrate($migration);
    }
  }
  
  function _migrate($script) {
    $pdo = $this->dormio->db;
    
    $parts = explode(DIRECTORY_SEPARATOR, realpath($script));
    $c = count($parts);
    
    $id = explode('_', basename($parts[$c-1], '.php'), 2);
    fputs(STDERR, "Migrating {$parts[$c-3]} {$id[1]} {$id[0]}...\n");
    
    try {
      $pdo->beginTransaction();
      $schema = include($script);
      $pdo->commit();
    } catch(Exception $e) {
      fputs(STDERR, "Trying to revert changes...\n");
      $pdo->rollback();
      throw $e;
    }
    
    // insert into migration table
    $migration = $this->dormio->get('Dormio_Migration');
    $migration->module = strtolower($parts[$c-3]);
    $migration->model = strtolower($id[1]);
    $migration->file = $parts[$c-1];
    $migration->applied = time();
    $migration->schema = $schema;
    $migration->save();
  }
  
  /**
  * Generates migration files for a module.
  * @param  string  $module   Module to generate migration files for
  */
  function generate($module) {
  
    // find the module path
    $migrations = false;
    foreach(Bantam::instance()->paths as $path) {
      if(basename($path) == $module) {
        $migrations = $path . DIRECTORY_SEPARATOR . "migrations";
        break;
      }
    }
    if(!$migrations) throw new Exception("No such module: {$module}");
    if(!file_exists($migrations)) mkdir($migrations);
    
    // make sure we have applied everything previous
    $this->module($path);
  
    $config = Dormio_Meta::config($module);
    if(isset($config['models'])) {
      foreach($config['models'] as $model) {
        $code = $this->generate($model);
        if($code) {
          $filename = $migrations . DIRECTORY_SEPARATOR . time() . "_" . $model . ".php";
          file_put_contents($filename, $code);
          fputs(STDERR, "Created migration for {$model} ({$filename})\n");
        } else {
          fputs(STDERR, "No modifications to model {$model}\n");
        }
      }
    } else {
      fputs(STDERR, "No models entry found in config for module {$module}\n");
    }
  }
  
  /**
  * Returns a code fragment to upgrade a specific model.
  * @param  string  $model    Model to upgrade
  * @return string            Valid PHP code to upgrade the model.
  */
  function upgrade_db($model) {
    $target = Dormio_Meta::get($model);
    
    try {
      // get the last applied migration
      $m = $this->dormio->manager('Dormio_Migration')->filter('model', '=', strtolower($model))->orderBy('-applied')->limit(1)->get();
      
      $current_schema = unserialize($m->schema);
      $sf = Dormio_Schema::factory($this->dormio->db, $current_schema);
      $sql = $sf->upgradeSQL($target->schema());
    } catch(PDOException $e) {
      $sf = Dormio_Schema::factory($this->dormio->db, $target);
      $sql = $sf->createSQL();
    }
    if(!$sql) return "";
    $output = "<?\n";
    $output .= "\$sql = <<< END_SQL\n";
    $output .= implode("\n", $sql);
    $output .= "\nEND_SQL;\n\n";
    $output .= <<< EOF
foreach(explode("\\n", \$sql) as \$line) {
  if(\$line!='' and \$line{0}!='#') \$pdo->exec(\$line);
}

// Need to return a serialized version of the schema

EOF;
    $output .= "return " . var_export(serialize($target->schema()), true) . ";\n?>\n";
    return $output;
  }
  
  function show() {
    $migrations = $this->dormio->manager('Dormio_Migration');
    foreach($migrations as $migration) {
      fprintf(STDERR, "%20s %20s %s\n", $migration->module, $migration->model, $migration->file);
    }
  }
}
?>