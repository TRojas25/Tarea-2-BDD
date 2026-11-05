# CASO-BDD

## Integrantes:

-Felipe Brito Alarcón - 202210034-6 - Tomás Rojas Villarroel - 202210032-k

## Requisitos

1. Base de Datos MySQL: La base de datos de la Tarea 2 **saludusm** debe estar corriendo localmente en **XAMPP**.
2. Docker y Docker Compose: Necesarios para levantar los 3 nodos de MongoDB.
3. Python 3.x: Con las librerías necesarias instaladas, si no están instaladas se puede usar el comando
   ```bash
   pip install pymongo mysql-connector-python
   ```

## Instrucciones para correcta ejecución 

1. Configuración de los nombres: Para que Python y docekr puedan conectarse correctamente con los nodos del cluster, se debe editar el archivo **hosts**. Para esto se debe abrir el bloc de notas como administrador y buscar el archivo `C:\Windows\System32\drivers\etc\hosts`. Posteriormente agregar la siguiente linea al final del: 127.0.0.1 mongo1 mongo2 mongo3.
2. Cluster en MongoDB: Abra una terminal en la raíz de esta carpeta (T3) y ejecute el archivo de configuración de Docker: docker-compose -f deploy.yml up -d. Lo que creara los 3 contenedores.
3. Iniciar la replicación: Una vez que los contenedores estén corriendo, ejecute el siguiente comando en la terminal para inicializar el Replica Set.
```powershell
docker exec -it mongo1 mongosh --port 30001 --eval "rs.initiate({_id: 'my-replica-set', members: [{_id: 0, host: 'mongo1:30001'}, {_id: 1, host: 'mongo2:30002'}, {_id: 2, host: 'mongo3:30003'}]})"
```
4. Ejecutar el cuadernillo de Jupyter

## Criterio de desempate en la consulta D 

El criterio de desempate aplicado en el aggregation pipeline en caso de que dos o más medicamentos posean la misma cantidad de recetas es un ordenamiento alfabético ascendente por el nombre del medicamento.

## Supuestos y Decisiones de Diseño

Para el correcto funcionamiento de los scripts de migración y consultas, se asumieron las siguientes condiciones sobre el entorno y los datos de origen (MySQL de la Tarea 2):

1. **Formato de Identificadores (Claves Naturales):** Se tomó la decisión de diseño de formatear el `id_cita` autoincremental de MySQL (ej. `1`) hacia un string alfanumérico en MongoDB (ej. `"CITA001"`). Esto se hizo para cumplir estrictamente con el formato estipulado en el documento de diseño documental y asegurar la semántica de la clave principal.


