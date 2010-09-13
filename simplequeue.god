# Monitor the status of the simplequeue workers
# 
# If there is a problem with the workers then God will catch it and report it.
# 
# run with: god -c /path/to/simplequeue.god -D

God::Contacts::Email.defaults do |d|
  d.from_email = "god@#{`hostname`}.jail.cloudmanaged.com"
  d.from_name = 'God Monitor'
  d.delivery_method = :sendmail
end

God.contact(:email) do |c|
  c.name = 'chrism'
  c.to_email = 'chrism@simpleweb.co.uk'
end

%w{default webhooks}.each do |queue|
  # Create a new watch for each queue worker
  God.watch do |w|
    w.name = "simplequeue-worker-#{queue}"
    w.dir = File.dirname(__FILE__)
    w.interval = 30.seconds # default
    w.env = { 'SIMPLEQUEUE_CONFIG' => queue }
    w.start = "php run.php"
    w.log = File.dirname(__FILE__) + "/log/simplequeue-#{queue}.log"

    # determine the state on startup
    w.transition(:init, { true => :up, false => :start }) do |on|
      on.condition(:process_running) do |c|
        c.notify = 'chrism'
        c.running = true
      end
    end

    # determine when process has finished starting
    w.transition([:start, :restart], :up) do |on|
      on.condition(:process_running) do |c|
        c.running = true
        c.interval = 5.seconds
      end

      # failsafe
      on.condition(:tries) do |c|
        c.times = 5
        c.transition = :start
        c.interval = 5.seconds
      end
    end

    # start if process is not running
    w.transition(:up, :start) do |on|
      on.condition(:process_exits) do |c|
        c.notify = 'chrism'
      end
      on.condition(:process_running) do |c|
        c.running = false
      end
    end
  end
end
