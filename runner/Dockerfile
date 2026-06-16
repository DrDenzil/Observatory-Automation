FROM golang:1.26-alpine AS builder
WORKDIR /src
COPY go.mod go.sum ./
RUN go mod download
COPY *.go ./
RUN CGO_ENABLED=0 go build -o /ekos-runner .

FROM alpine:3.20
# qdbus (from qt5-qttools) is needed when simulator: false to drive real KStars.
RUN apk add --no-cache ca-certificates
COPY --from=builder /ekos-runner /usr/local/bin/ekos-runner
COPY runner.yaml.example /etc/ekos-runner/runner.yaml
EXPOSE 9090
ENTRYPOINT ["ekos-runner", "-config", "/etc/ekos-runner/runner.yaml"]
