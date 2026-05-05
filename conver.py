import os

# Configuramos las rutas para que sean dinámicas
# Esto ayuda a que el script sepa dónde está parado
DIRECTORIO_ACTUAL = os.path.dirname(os.path.abspath(__file__))
ARCHIVO_SALIDA = os.path.join(DIRECTORIO_ACTUAL, "ContaVision.txt")

# Definimos qué queremos capturar
EXTENSIONES_VALIDAS = {'.js', '.jsx', '.ts', '.tsx', '.css', '.html', '.json', '.php', 'mysql', '.sql', '.md', '.yml', '.yaml', '.env', '.env.example'}  # Puedes agregar más según necesites
CARPETAS_IGNORAR = {'node_modules', '.git', 'dist', 'build', 'config', 'vendor', '.env', '.env.example', '.gitignore',} # 'public/assets/js/calculadora_automatica.js'  # 'public/assets/js/calculadora_automatica.js'  # 'public/assets/js/calculadora_automatica.js'  # 'public/assets/js/calculadora_automatica.js'  # 'public/assets/js/calculadora_automatica.js'              

def consolidar():
    print(f"--- Iniciando búsqueda en: {DIRECTORIO_ACTUAL} ---")
    archivos_encontrados = 0

    try:
        with open(ARCHIVO_SALIDA, 'w', encoding='utf-8') as f_out:
            for raiz, dirs, archivos in os.walk(DIRECTORIO_ACTUAL):
                # Filtramos carpetas pesadas para ganar velocidad
                dirs[:] = [d for d in dirs if d not in CARPETAS_IGNORAR]
                
                for nombre in archivos:
                    if any(nombre.endswith(ext) for ext in EXTENSIONES_VALIDAS):
                        ruta_completa = os.path.join(raiz, nombre)
                        ruta_relativa = os.path.relpath(ruta_completa, DIRECTORIO_ACTUAL)
                        
                        f_out.write(f"\n{'='*50}\n")
                        f_out.write(f"ARCHIVO: {ruta_relativa}\n")
                        f_out.write(f"{'='*50}\n\n")
                        
                        with open(ruta_completa, 'r', encoding='utf-8') as f_in:
                            f_out.write(f_in.read())
                        
                        archivos_encontrados += 1
                        print(f"Agregado: {ruta_relativa}")

        if archivos_encontrados > 0:
            print(f"\n¡Éxito! Se creó '{ARCHIVO_SALIDA}' con {archivos_encontrados} archivos.")
        else:
            print("\nAtención: No se encontraron archivos con las extensiones indicadas.")
            
    except Exception as e:
        print(f"Vaya, ocurrió un detalle técnico: {e}")

if __name__ == "__main__":
    consolidar()