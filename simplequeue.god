# Monitor the status of the simplequeue workers
# 
# If there is a problem with the workers then God will catch it and report it.
# 
# run with: god -c /path/to/simplequeue.god -D


%w{default webhooks}.each do |queue|
  # Create a new watch for each queue worker
  God.watch do |w|
    w.name = "simplequeue-worker-#{queue}"
    w.dir = '/home/simpleweb/scripts/simplequeue'
    w.interval = 30.seconds # default      
    w.start = "SIMPLEQUEUE_CONFIG=#{queue} php run.php"
    w.start_grace = 10.seconds
    w.restart_grace = 10.seconds

    w.behavior(:clean_pid_file)

    w.start_if do |start|
      start.condition(:process_running) do |c|
        c.interval = 5.seconds
        c.running = false
      end
    end

    w.restart_if do |restart|
      restart.condition(:memory_usage) do |c|
        c.above = 150.megabytes
        c.times = [3, 5] # 3 out of 5 intervals
      end

      restart.condition(:cpu_usage) do |c|
        c.above = 50.percent
        c.times = 5
      end
    end

    # lifecycle
    w.lifecycle do |on|
      on.condition(:flapping) do |c|
        c.to_state = [:start, :restart]
        c.times = 5
        c.within = 5.minute
        c.transition = :unmonitored
        c.retry_in = 10.minutes
        c.retry_times = 5
        c.retry_within = 2.hours
      end
    end
  end
end
