from flask import Flask, request, jsonify
import subprocess
from flask_cors import CORS  # Import CORS
import psutil  # Import psutil to manage processes

app = Flask(__name__)
CORS(app)  # Enable CORS for all routes

@app.route('/start', methods=['POST'])
def start_backend():
    # This will run bot.py in the background
    try:
        subprocess.Popen(["python", "bot.py", "--mode", "camera"])  # or "screen"
        return jsonify({"status": "started"}), 200
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/stop', methods=['POST'])
def stop_backend():
    # This will stop the bot.py process
    try:
        for proc in psutil.process_iter(['pid', 'name', 'cmdline']):
            if "python" in proc.info['name'] and "bot.py" in proc.info['cmdline']:
                proc.terminate()  # Terminate the process
                return jsonify({"status": "stopped"}), 200
        return jsonify({"error": "bot.py process not found"}), 404
    except Exception as e:
        return jsonify({"error": str(e)}), 500

if __name__ == '__main__':
    app.run(port=5000)
