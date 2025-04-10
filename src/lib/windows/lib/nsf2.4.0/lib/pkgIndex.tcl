package ifneeded nx::help 1.0 "[list source [file join $dir nx-help.tcl]]; [list package provide nx::help 1.0]"
package ifneeded nx::pp 1.0 "[list source [file join $dir nx-pp.tcl]]; [list package provide nx::pp 1.0]"
package ifneeded nx::shell 1.1 "[list source [file join $dir nx-shell.tcl]]; [list package provide nx::shell 1.1]"
package ifneeded nx::test 1.0 "[list source [file join $dir nx-test.tcl]]; [list package provide nx::test 1.0]"
package ifneeded nx::trait 0.4 "[list source [file join $dir nx-traits.tcl]]; [list package provide nx::trait 0.4]"
package ifneeded nx::trait::callback 1.0 "[list source [file join $dir nx-callback.tcl]]; [list package provide nx::trait::callback 1.0]"
package ifneeded nx::volatile 1.0 "[list source [file join $dir nx-volatile.tcl]]; [list package provide nx::volatile 1.0]"
package ifneeded nx::zip 1.3 "[list source [file join $dir nx-zip.tcl]]; [list package provide nx::zip 1.3]"
# -*- Tcl -*-
namespace eval ::nsf {
  set traitIndex(nx::trait::callback) {script {package require nx::trait::callback}}
} 

