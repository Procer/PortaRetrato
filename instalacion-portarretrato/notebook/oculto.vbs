' Lanza un .bat sin mostrar ventana (lo usa el Programador de tareas cada minuto).
CreateObject("WScript.Shell").Run """" & WScript.Arguments(0) & """", 0, False
