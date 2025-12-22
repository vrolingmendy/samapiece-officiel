FROM python:3.11-slim

ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends build-essential libjpeg-dev zlib1g-dev \
    && rm -rf /var/lib/apt/lists/*

COPY samapiece/requirements.txt /app/requirements.txt
RUN pip install --no-cache-dir -r requirements.txt

COPY samapiece/ /app/
RUN mkdir -p /app/uploads

EXPOSE 5000

ENV FLASK_APP=app.py
ENV FLASK_RUN_HOST=0.0.0.0
ENV FLASK_RUN_PORT=5000

CMD ["flask", "run", "--host=0.0.0.0", "--port=5000", "--app", "app.py"]
